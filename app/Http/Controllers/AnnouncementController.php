<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\MahasiswaTa;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    /**
     * Daftar pengumuman (dosen/admin: dikirim + laporan baca; mahasiswa: diterima).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isDosen() || $user->isAdmin()) {
            $announcements = Announcement::where('sender_id', $user->id)
                ->withCount('recipients')
                ->orderByDesc('created_at')
                ->get();
            return view('announcements.index-sender', compact('announcements', 'user'));
        }

        // Mahasiswa: pengumuman yang ditujukan padanya.
        $announcements = $user->announcements()
            ->with('sender')
            ->orderByDesc('created_at')
            ->get();

        return view('announcements.index-recipient', compact('announcements', 'user'));
    }

    public function create(): View
    {
        $user = auth()->user();
        abort_unless($user->isDosen() || $user->isAdmin(), 403);

        // Daftar mahasiswa bimbingan utk target manual / default.
        $bimbingan = MahasiswaTa::where('pembimbing_1_id', $user->id)
            ->orWhere('pembimbing_2_id', $user->id)
            ->with('mahasiswa')
            ->get();

        return view('announcements.create', compact('bimbingan', 'user'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isDosen() || $user->isAdmin(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'target_mode' => ['required', 'in:all,manual'],
            'target_mahasiswa' => ['nullable', 'array'],
            'target_mahasiswa.*' => ['integer'],
        ]);

        // Tentukan penerima.
        if ($validated['target_mode'] === 'manual') {
            $recipientIds = $request->input('target_mahasiswa', []);
            $query = MahasiswaTa::whereIn('id', $recipientIds);
            // Filter: dosen hanya bisa kirim ke mahasiswa bimbingannya (P1/P2).
            // Admin bebas memilih semua mahasiswa.
            if (!$user->isAdmin()) {
                $query->where(fn ($q) => $q->where('pembimbing_1_id', $user->id)
                    ->orWhere('pembimbing_2_id', $user->id));
            }
            $recipients = $recipientIds ? $query->pluck('user_id') : collect();
        } else {
            // Semua mahasiswa bimbingan dosen ini (admin: semua mahasiswa).
            $query = MahasiswaTa::query();
            if (!$user->isAdmin()) {
                $query->where(fn ($q) => $q->where('pembimbing_1_id', $user->id)
                    ->orWhere('pembimbing_2_id', $user->id));
            }
            $recipients = $query->pluck('user_id');
        }

        $announcement = Announcement::create([
            'sender_id' => $user->id,
            'institution_id' => Feature::isInstitution() ? $user->institution_id : null,
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        foreach ($recipients->unique() as $uid) {
            $announcement->recipients()->attach($uid);
            if ($u = User::find($uid)) {
                $this->bestEffort(fn () => $u->notify(new \App\Notifications\ActivityNotification(
                    "📢 Pengumuman: {$announcement->title}",
                    route('announcements.index'),
                    'Pengumuman Baru',
                )));
            }
        }

        return redirect()->route('announcements.index')
            ->with('success', "Pengumuman terkirim ke {$recipients->unique()->count()} mahasiswa.");
    }

    /**
     * Laporan baca pengumuman (dosen/admin).
     */
    public function report(Announcement $announcement): View
    {
        abort_unless(auth()->id() === $announcement->sender_id || auth()->user()->isAdmin(), 403);

        $recipients = $announcement->recipients()->get();
        $read = $recipients->filter(fn ($r) => $r->pivot->read_at);
        $unread = $recipients->filter(fn ($r) => !$r->pivot->read_at);

        return view('announcements.report', compact('announcement', 'read', 'unread'));
    }

    /**
     * Tandai dibaca (mahasiswa).
     */
    public function markRead(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();
        $pivot = $announcement->recipients()->where('users.id', $user->id)->first();

        if ($pivot && !$pivot->pivot->read_at) {
            $announcement->recipients()->updateExistingPivot($user->id, ['read_at' => now()]);
        }

        return back()->with('success', 'Pengumuman ditandai dibaca.');
    }

    /**
     * Kirim ulang notifikasi ke yang belum baca (tanpa duplikat pengumuman).
     */
    public function remindUnread(Announcement $announcement): RedirectResponse
    {
        abort_unless(auth()->id() === $announcement->sender_id || auth()->user()->isAdmin(), 403);

        $unread = $announcement->recipients()
            ->wherePivotNull('read_at')
            ->get();

        foreach ($unread as $u) {
            $this->bestEffort(fn () => $u->notify(new \App\Notifications\ActivityNotification(
                "📢 Pengingat: {$announcement->title}",
                route('announcements.index'),
                'Pengingat Pengumuman',
            )));
        }

        return back()->with('success', "Pengingat dikirim ke {$unread->count()} mahasiswa.");
    }
}
