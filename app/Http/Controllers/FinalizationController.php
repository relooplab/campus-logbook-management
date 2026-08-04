<?php

namespace App\Http\Controllers;

use App\Models\FinalizationApproval;
use App\Models\MahasiswaTa;
use App\Models\ThesisFinalization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinalizationController extends Controller
{
    private const ITEMS = ['abstrak', 'keyword', 'cover', 'pengesahan', 'full_file'];

    public function index(Request $request, MahasiswaTa $mahasiswaTa): View
    {
        abort_unless($mahasiswaTa->isMember($request->user()), 403);
        $finalization = $mahasiswaTa->finalization ?? $mahasiswaTa->finalization()->create([]);
        return view('finalization.index', compact('mahasiswaTa', 'finalization'));
    }

    public function store(Request $request, MahasiswaTa $mahasiswaTa): RedirectResponse
    {
        abort_unless($mahasiswaTa->isMember($request->user()), 403);
        $isKp = $mahasiswaTa->isKp();
        $items = $isKp ? ['full_file'] : self::ITEMS;

        $rules = [];
        if (!$isKp) {
            $rules['abstrak'] = ['required', 'string'];
            $rules['keyword'] = ['required', 'string', 'max:255'];
            $rules['cover'] = ['required', 'file', 'mimes:pdf', 'max:25600'];
            $rules['pengesahan'] = ['required', 'file', 'mimes:pdf', 'max:25600'];
        }
        $rules['full_file'] = ['required', 'file', 'mimes:pdf', 'max:25600'];

        $request->validate($rules);
        $finalization = $mahasiswaTa->finalization ?? $mahasiswaTa->finalization()->create([]);

        $data = [];
        if (!$isKp) {
            $data['abstrak'] = $request->input('abstrak');
            $data['keyword'] = $request->input('keyword');
            $data['cover_path'] = $this->storePdf($request->file('cover'), 'cover', $mahasiswaTa->id);
            $data['cover_original_name'] = $request->file('cover')->getClientOriginalName();
            $data['pengesahan_path'] = $this->storePdf($request->file('pengesahan'), 'pengesahan', $mahasiswaTa->id);
            $data['pengesahan_original_name'] = $request->file('pengesahan')->getClientOriginalName();
        }
        $data['full_file_path'] = $this->storePdf($request->file('full_file'), 'full', $mahasiswaTa->id);
        $data['full_file_original_name'] = $request->file('full_file')->getClientOriginalName();

        foreach ($items as $item) {
            $data[$item.'_status'] = 'submitted';
        }
        $finalization->update($data);

        foreach ([$mahasiswaTa->pembimbing_1_id, $mahasiswaTa->pembimbing_2_id] as $pid) {
            if ($pid) {
                foreach ($items as $item) {
                    FinalizationApproval::updateOrCreate(
                        ['finalization_id' => $finalization->id, 'item' => $item, 'pembimbing_id' => $pid],
                        ['status' => 'pending']
                    );
                }
            }
        }

        return back()->with('success', 'Finalisasi dikirim untuk persetujuan dosen.');
    }

    public function review(Request $request): View
    {
        $user = $request->user();
        $finalizations = ThesisFinalization::whereHas('mahasiswaTa', fn ($q) => $q->where('pembimbing_1_id', $user->id)->orWhere('pembimbing_2_id', $user->id))
            ->with(['mahasiswaTa.mahasiswa', 'approvals'])
            ->get();
        return view('finalization.review', compact('finalizations', 'user'));
    }

    public function approveItem(Request $request, ThesisFinalization $finalization, string $item): RedirectResponse
    {
        $this->authorizePembimbing($request->user(), $finalization);
        $this->validateItem($finalization, $item);
        $approval = $this->getApproval($finalization, $item, $request->user()->id);
        $approval->update(['status' => 'approved']);

        $required = $this->requiredApprovals($finalization);
        $allApproved = $finalization->approvals()->where('item', $item)->where('status', 'approved')->count() >= $required;
        if ($allApproved) {
            $finalization->update([$item.'_status' => 'approved']);
            $this->maybeUnlockMilestone($finalization);
        }

        return back()->with('success', "Item '{$item}' disetujui.");
    }

    public function rejectItem(Request $request, ThesisFinalization $finalization, string $item): RedirectResponse
    {
        $this->authorizePembimbing($request->user(), $finalization);
        $this->validateItem($finalization, $item);
        $approval = $this->getApproval($finalization, $item, $request->user()->id);
        $approval->update(['status' => 'rejected']);
        $finalization->update([$item.'_status' => 'rejected']);
        return back()->with('success', "Item '{$item}' ditolak.");
    }

    public function unlockItem(Request $request, ThesisFinalization $finalization, string $item): RedirectResponse
    {
        $this->authorizePembimbing($request->user(), $finalization);
        $this->validateItem($finalization, $item);
        $finalization->update([$item.'_status' => 'draft']);
        $finalization->approvals()->where('item', $item)->update(['status' => 'pending']);
        return back()->with('success', "Item '{$item}' dibuka kembali.");
    }

    /**
     * Pastikan $item termasuk daftar item yang berlaku untuk jenis program (TA/KP).
     */
    private function validateItem(ThesisFinalization $finalization, string $item): void
    {
        $ta = $finalization->mahasiswaTa;
        $allowed = $ta && $ta->isKp() ? ['full_file'] : self::ITEMS;
        abort_unless(in_array($item, $allowed, true), 404, "Item '{$item}' tidak dikenal.");
    }

    /**
     * Jumlah approval yang dibutuhkan = jumlah pembimbing yang benar-benar ada
     * (pembimbing_2 opsional, jadi tidak selalu 2).
     */
    private function requiredApprovals(ThesisFinalization $finalization): int
    {
        $ta = $finalization->mahasiswaTa;
        return $ta ? count(array_filter([$ta->pembimbing_1_id, $ta->pembimbing_2_id])) : 1;
    }

    public function inputNilai(Request $request, ThesisFinalization $finalization): RedirectResponse
    {
        $this->authorizePembimbing($request->user(), $finalization);
        $validated = $request->validate(['nilai' => ['required', 'numeric', 'min:0', 'max:100']]);
        $finalization->update(['nilai' => $validated['nilai']]);
        return back()->with('success', 'Nilai berhasil disimpan.');
    }

    private function authorizePembimbing($user, ThesisFinalization $finalization): void
    {
        $ta = $finalization->mahasiswaTa;
        abort_unless($ta && ($ta->pembimbing_1_id === $user->id || $ta->pembimbing_2_id === $user->id), 403);
    }

    private function getApproval(ThesisFinalization $finalization, string $item, int $pid): FinalizationApproval
    {
        return FinalizationApproval::firstOrCreate(
            ['finalization_id' => $finalization->id, 'item' => $item, 'pembimbing_id' => $pid],
            ['status' => 'pending']
        );
    }

    private function storePdf($file, string $dir, int $taId): string
    {
        return $file->storeAs('finalization/'.$taId.'/'.$dir, \Illuminate\Support\Str::uuid().'.pdf', 'local');
    }

    private function maybeUnlockMilestone(ThesisFinalization $finalization): void
    {
        if ($finalization->allItemsApproved()) {
            $ta = $finalization->mahasiswaTa;
            if ($ta && $ta->isTa() && $ta->fase === 'sidang') {
                $ta->update(['fase' => 'achievement']);
            }
        }
    }
}