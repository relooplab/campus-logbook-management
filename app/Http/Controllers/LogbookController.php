<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLogbookEntryRequest;
use App\Http\Requests\StoreRevisiRequest;
use App\Http\Requests\UpdateLogbookEntryRequest;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\PdfComment;
use App\Services\StorageUsageService;
use App\Support\ProgramContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LogbookController extends Controller
{
    // ---------------------------------------------------------------- create

    public function create(Request $request): View
    {
        $ta = ProgramContext::resolve($request->user(), $request);
        abort_unless($ta, 403, 'Anda belum memiliki program (TA/KP). Pilih dosen terlebih dahulu.');

        // Auto-fill: sesi berikutnya & topik sebelumnya.
        $lastEntry = $ta->entries()
            ->where('jenis', LogbookEntry::JENIS_LOGBOOK)
            ->orderByDesc('sesi_ke')
            ->first();

        $nextSesi = ($lastEntry?->sesi_ke ?? 0) + 1;
        $lastTopik = $lastEntry?->topik;

        return view('logbook.create', compact('ta', 'nextSesi', 'lastTopik'));
    }

    public function createRevisi(Request $request): View
    {
        $ta = ProgramContext::resolve($request->user(), $request);
        abort_unless($ta, 403, 'Anda belum memiliki program (TA/KP). Pilih dosen terlebih dahulu.');

        // Mahasiswa dapat membuat entri revisi tanpa harus ada logbook dulu.
        // Daftar parent (entri berstatus revisi) tetap tersedia untuk dipilih.
        $parents = $ta->entries()
            ->where('status', LogbookEntry::STATUS_REVISI)
            ->whereDoesntHave('revisionChildren')
            ->with('comments.user')
            ->latest('reviewed_at')
            ->get();

        $selectedParentId = $request->query('parent_entry_id');

        return view('logbook.create-revisi', compact('ta', 'parents', 'selectedParentId'));
    }

    public function store(StoreLogbookEntryRequest $request): RedirectResponse
    {
        $ta = ProgramContext::resolve($request->user(), $request);
        abort_unless($ta, 403);

        $data = $request->validated();

        // Tombol "Kirim ke Pembimbing" langsung mengirim (bukan draf).
        // Jika program masih pending_approval, paksa draft (belum bisa submit ke dosen).
        $submit = $ta->status_ta === \App\Models\MahasiswaTa::STATUS_AKTIF && $request->boolean('submit');
        $sesiKe = $ta->entries()
            ->where('jenis', LogbookEntry::JENIS_LOGBOOK)
            ->count() + 1;

        // Buat entry dulu agar path unik {entry_id}/{uuid} bisa memakai id.
        $entry = $ta->entries()->create([
            'dosen_id' => $ta->pembimbing_1_id,
            'tanggal_bimbingan' => $data['tanggal_bimbingan'],
            'topik' => $data['topik'],
            'sesi_ke' => $sesiKe,
            'jenis' => LogbookEntry::JENIS_LOGBOOK,
            'progres_kendala' => $data['progres_kendala'],
            'status' => $submit ? LogbookEntry::STATUS_SUBMITTED : LogbookEntry::STATUS_DRAFT,
            'submitted_at' => $submit ? now() : null,
        ]);

        // Simpan lampiran dengan path unik + nama asli.
        if ($request->hasFile('lampiran')) {
            // Cek kuota dosen pembimbing (pembimbing 1, fallback pembimbing 2).
            $dosen = $ta->pembimbing1 ?: $ta->pembimbing2;
            if ($dosen) {
                app(StorageUsageService::class)->assertCanUpload($dosen, $request->file('lampiran')->getSize());
            }

            $entry->update([
                'lampiran_path' => $this->storeUniqueFile($request->file('lampiran'), 'lampiran', $entry->id),
                'lampiran_original_name' => $request->file('lampiran')->getClientOriginalName(),
                'lampiran_size' => $request->file('lampiran')->getSize(),
            ]);
        }

        if ($submit) {
            $this->bestEffort(fn () => \App\Events\EntryStatusChanged::dispatch($entry, 'Ada entri baru menunggu review.'));
            $entry->notifyDosen(
                'Entri logbook sesi '.$entry->sesi_ke.' baru dikirim oleh mahasiswa.',
                route('logbook.show', $entry),
                'Entri Baru Menunggu Review',
            );
        }

        return redirect()->route('logbook.index')
            ->with('success', $submit
                ? 'Entri logbook dikirim ke dosen.'
                : 'Entri logbook tersimpan sebagai draf.');
    }

    public function storeRevisi(StoreRevisiRequest $request): RedirectResponse
    {
        $ta = ProgramContext::resolve($request->user(), $request);
        abort_unless($ta, 403);

        $data = $request->validated();
        // Jika program masih pending_approval, paksa draft (belum bisa submit ke dosen).
        $submit = $ta->status_ta === \App\Models\MahasiswaTa::STATUS_AKTIF && $request->boolean('submit');

        [$parent, $entry] = DB::transaction(function () use ($ta, $data, $submit) {
            // Mahasiswa dapat membuat entri revisi tanpa harus ada logbook dulu.
            // Jika parent dipilih, validasi & tautkan ke entri induk.
            $parent = null;
            if (!empty($data['parent_entry_id'])) {
                $parent = $ta->entries()
                    ->whereKey($data['parent_entry_id'])
                    ->where('status', LogbookEntry::STATUS_REVISI)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($parent->revisionChildren()
                    ->whereIn('status', [LogbookEntry::STATUS_DRAFT, LogbookEntry::STATUS_SUBMITTED, LogbookEntry::STATUS_REVISI])
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'parent_entry_id' => 'Entri induk sudah memiliki revisi aktif. Pilih entri induk lain.',
                    ]);
                }
            }

            // Buat entry dulu agar path unik bisa memakai id.
            $entry = $ta->entries()->create([
                'parent_entry_id' => $parent?->id,
                'revision_round' => $parent ? ($parent->revision_round ?? 0) + 1 : null,
                'sesi_ke' => 0,
                'jenis' => LogbookEntry::JENIS_REVISI,
                'dosen_id' => $parent?->dosen_id ?: $parent?->reviewDosen()?->id ?: $ta->pembimbing_1_id,
                'topik' => $parent?->topik,
                'progres_kendala' => $data['progres_kendala'],
                'tanggal_pengiriman' => $data['tanggal_pengiriman'],
                'status' => $submit ? LogbookEntry::STATUS_SUBMITTED : LogbookEntry::STATUS_DRAFT,
                'submitted_at' => $submit ? now() : null,
            ]);

            return [$parent, $entry];
        });

        // Cek kuota dosen pembimbing sebelum menyimpan lampiran revisi.
        $dosen = $ta->pembimbing1 ?: $ta->pembimbing2;
        if ($dosen && $request->hasFile('lampiran')) {
            app(StorageUsageService::class)->assertCanUpload($dosen, $request->file('lampiran')->getSize());
        }

        $entry->update([
            'lampiran_path' => $this->storeUniqueFile($request->file('lampiran'), 'lampiran', $entry->id),
            'lampiran_original_name' => $request->file('lampiran')->getClientOriginalName(),
            'lampiran_size' => $request->file('lampiran')->getSize(),
            'riwayat_perbaikan' => $data['riwayat_perbaikan'],
        ]);

        // Generate PDF catatan perbaikan otomatis dari tabel riwayat perbaikan.
        $this->generateCatatanPerbaikanPdf($entry);

        $commentIds = collect($data['addressed_comment_ids'] ?? [])->filter()->values();
        if ($submit && $parent && $commentIds->isNotEmpty()) {
            $parent->comments()
                ->whereIn('id', $commentIds)
                ->update([
                    'resolution_status' => PdfComment::STATUS_ADDRESSED,
                    'is_resolved' => false,
                ]);
        }

        if ($submit) {
            $this->bestEffort(fn () => \App\Events\EntryStatusChanged::dispatch($entry, 'Ada entri revisi baru menunggu review.'));
            $entry->notifyDosen(
                'Entri revisi baru dikirim oleh mahasiswa.',
                route('logbook.show', $entry),
                'Entri Baru Menunggu Review',
            );
        }

        return redirect()->route('logbook.show', $entry)
            ->with('success', $submit
                ? 'Entri revisi dikirim ke dosen.'
                : 'Entri revisi tersimpan sebagai draf.');
    }

    // ---------------------------------------------------------------- index

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['status', 'jenis', 'date_from', 'date_to', 'keyword']);

        if ($user->isMahasiswa()) {
            $ta = ProgramContext::resolve($user, $request);
            $query = $ta
                ? $ta->entries()->with('comments')
                : LogbookEntry::query()->whereRaw('1 = 0');
        } elseif ($user->isDosen()) {
            // Semua entry dari TA yang dibimbing dosen ini.
            // TA di mana dosen adalah pembimbing ATAU penguji.
            $taIds = MahasiswaTa::where('pembimbing_1_id', $user->id)
                ->orWhere('pembimbing_2_id', $user->id)
                ->orWhere('penguji_1_id', $user->id)
                ->orWhere('penguji_2_id', $user->id)
                ->pluck('id');

            // TA dari dosen lain yang punya hubungan langsung (grup/TA bersama).
            $relatedTaIds = MahasiswaTa::where(function ($q) use ($user) {
                $q->whereIn('pembimbing_1_id', $user->relatedDosenIds())
                    ->orWhereIn('pembimbing_2_id', $user->relatedDosenIds())
                    ->orWhereIn('penguji_1_id', $user->relatedDosenIds())
                    ->orWhereIn('penguji_2_id', $user->relatedDosenIds());
            })->pluck('id');

            $query = LogbookEntry::where(fn ($q) => $q->whereIn('mahasiswa_ta_id', $taIds)
                    ->orWhereIn('mahasiswa_ta_id', $relatedTaIds)
                    ->orWhere('dosen_id', $user->id))
                ->with(['mahasiswaTa.mahasiswa']);
        } else {
            $query = LogbookEntry::with(['mahasiswaTa.mahasiswa']);
        }

        // Filter kombinasi memakai when() query builder (spesifikasi Fase 7).
        $query->when($request->filled('status'), fn ($q) => $q->status($request->query('status')))
            ->when($request->filled('jenis'), fn ($q) => $q->jenis($request->query('jenis')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('tanggal_bimbingan', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('tanggal_bimbingan', '<=', $request->query('date_to')))
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $kw = $request->query('keyword');
                $q->where(function ($qq) use ($kw) {
                    $qq->where('topik', 'like', "%{$kw}%")
                        ->orWhere('progres_kendala', 'like', "%{$kw}%")
                        ->orWhereHas('mahasiswaTa.mahasiswa', fn ($m) => $m->where('name', 'like', "%{$kw}%"));
                });
            });

        $entries = $query->latest()->paginate(20)->withQueryString();

        return view('logbook.index', compact('entries', 'filters'));
    }

    // ---------------------------------------------------------------- feedback page

    /**
     * Halaman "Logbook Feedback": semua feedback dosen untuk mahasiswa ini.
     * Kolom: tanggal | topik | feedback | note (dapat diisi mahasiswa).
     */
    public function feedback(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isMahasiswa(), 403);

        $ta = ProgramContext::resolve($user, $request);
        abort_unless($ta, 403, 'Anda belum memiliki program (TA/KP). Pilih dosen terlebih dahulu.');

        $feedbacks = $ta->entries()
            ->whereNotNull('feedback_dosen')
            ->with('dosen', 'actionItems')
            ->latest('reviewed_at')
            ->get()
            ->filter(function ($e) {
                return filled($e->feedback_dosen);
            })
            ->values();

        return view('logbook.feedback', compact('feedbacks'));
    }

    /**
     * Simpan catatan (note) mahasiswa untuk feedback dosen tertentu.
     * Pemilik TA dapat mengisi/mengubah note kapan saja (tidak terbatas
     * oleh status entri draft/revisi).
     */
    public function updateFeedbackNote(Request $request, LogbookEntry $logbook): RedirectResponse
    {
        $this->authorize('owner', $logbook);

        $validated = $request->validate([
            'feedback_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $logbook->update(['feedback_note' => $validated['feedback_note'] ?? null]);

        return back()->with('success', 'Catatan feedback berhasil disimpan.');
    }

    // ---------------------------------------------------------------- show

    public function show(Request $request, LogbookEntry $logbook): View
    {
        $this->authorize('view', $logbook);
        $logbook->load([
            'mahasiswaTa.mahasiswa', 'mahasiswaTa.pembimbing1', 'mahasiswaTa.pembimbing2', 'dosen', 'comments.user',
            'parentEntry.comments.user', 'parentEntry.parentEntry', 'revisionChildren',
            'actionItems',
        ]);

        $draftPdf = $logbook->lampiran_path ? Storage::disk('local')->path($logbook->lampiran_path) : null;
        $catatanPdf = $logbook->catatan_perbaikan_path ? Storage::disk('local')->path($logbook->catatan_perbaikan_path) : null;

        return view('logbook.show', compact('logbook', 'draftPdf', 'catatanPdf'));
    }

    // ---------------------------------------------------------------- edit

    public function edit(Request $request, LogbookEntry $logbook): View
    {
        $this->authorize('update', $logbook);

        return view('logbook.edit', compact('logbook'));
    }

    public function update(UpdateLogbookEntryRequest $request, LogbookEntry $logbook): RedirectResponse
    {
        $this->authorize('update', $logbook);

        $data = $request->validated();
        $resolvedCount = 0;

        if ($request->hasFile('lampiran')) {
            $oldPath = $logbook->lampiran_path;
            $newPath = $this->storeUniqueFile($request->file('lampiran'), 'lampiran', $logbook->id);

            $logbook->update([
                'lampiran_path' => $newPath,
                'lampiran_original_name' => $request->file('lampiran')->getClientOriginalName(),
                'lampiran_size' => $request->file('lampiran')->getSize(),
            ]);

            // File lama DI-ORPHAN (tidak dihapus). Auto-resolve komentar pada
            // file_type yang diganti (komentar kontekstual terhadap versi file).
            $resolvedCount = $this->resolveCommentsForType($logbook, PdfComment::FILE_TYPE_DRAFT);
            $this->logAttachmentChange($logbook, 'lampiran_path', $oldPath, $newPath, $resolvedCount);
        }

        if ($logbook->jenis === LogbookEntry::JENIS_REVISI) {
            $logbook->update([
                'tanggal_pengiriman' => $data['tanggal_pengiriman'],
                'progres_kendala' => $data['progres_kendala'],
                'riwayat_perbaikan' => $data['riwayat_perbaikan'],
            ]);

            // Generate ulang PDF catatan perbaikan dari tabel.
            $this->generateCatatanPerbaikanPdf($logbook);
        } else {
            $logbook->update([
                'tanggal_bimbingan' => $data['tanggal_bimbingan'],
                'topik' => $data['topik'],
                'progres_kendala' => $data['progres_kendala'],
            ]);
        }

        return redirect()->route('logbook.show', $logbook)
            ->with('success', $resolvedCount > 0
                ? "Entri berhasil diperbarui. {$resolvedCount} komentar PDF di-resolve otomatis karena file diganti."
                : 'Entri berhasil diperbarui.');
    }

    /**
     * Hapus lampiran (hanya status draft/revisi). File lama di-orphan.
     */
    public function removeLampiran(Request $request, LogbookEntry $logbook): RedirectResponse
    {
        $this->authorize('update', $logbook);

        if ($logbook->jenis === LogbookEntry::JENIS_REVISI) {
            return back()->with('error', 'Entri revisi wajib memiliki file perbaikan. Gunakan "Ganti" untuk mengganti file.');
        }

        $oldPath = $logbook->lampiran_path;
        $logbook->update(['lampiran_path' => null, 'lampiran_original_name' => null, 'lampiran_size' => null]);
        $resolvedCount = $this->resolveCommentsForType($logbook, PdfComment::FILE_TYPE_DRAFT);
        $this->logAttachmentChange($logbook, 'lampiran_path', $oldPath, null, $resolvedCount);

        return back()->with('success', 'Lampiran dihapus.');
    }

    /**
     * Hapus catatan perbaikan (hanya status draft/revisi). File lama di-orphan.
     */
    public function removeCatatan(Request $request, LogbookEntry $logbook): RedirectResponse
    {
        $this->authorize('update', $logbook);

        if ($logbook->jenis === LogbookEntry::JENIS_REVISI) {
            return back()->with('error', 'Entri revisi wajib memiliki catatan perbaikan.');
        }

        $oldPath = $logbook->catatan_perbaikan_path;
        $logbook->update(['catatan_perbaikan_path' => null, 'catatan_original_name' => null, 'catatan_perbaikan_size' => null]);
        $resolvedCount = $this->resolveCommentsForType($logbook, PdfComment::FILE_TYPE_CATATAN);
        $this->logAttachmentChange($logbook, 'catatan_perbaikan_path', $oldPath, null, $resolvedCount);

        return back()->with('success', 'Catatan perbaikan dihapus.');
    }

    /**
     * Simpan file ke path unik {dir}/{entry_id}/{uuid}.ext (anti tabrakan nama).
     */
    private function storeUniqueFile($file, string $dir, ?int $entryId): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'pdf';
        $id = $entryId ?: uniqid('e', false);
        $name = $id.'/'.(string) \Illuminate\Support\Str::uuid().'.'.$ext;

        return $file->storeAs($dir.'/'.$id, basename($name), 'local');
    }

    /**
     * Generate PDF catatan perbaikan otomatis dari tabel riwayat_perbaikan.
     * PDF disimpan ke catatan_perbaikan_path (agar tampil di tab "Catatan"
     * PDF viewer & bisa diunduh). File lama di-orphan (tidak dihapus).
     */
    private function generateCatatanPerbaikanPdf(LogbookEntry $logbook): void
    {
        if (empty($logbook->riwayat_perbaikan)) {
            return;
        }

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.catatan-perbaikan', [
                'logbook' => $logbook,
                'riwayat' => $logbook->riwayat_perbaikan,
                'pesan' => $logbook->progres_kendala,
            ]);

            $output = $pdf->output();
            $path = 'catatan/'.$logbook->id.'/'.(string) \Illuminate\Support\Str::uuid().'.pdf';
            Storage::disk('local')->put($path, $output);

            $logbook->update([
                'catatan_perbaikan_path' => $path,
                'catatan_original_name' => 'catatan-perbaikan-'.$logbook->id.'.pdf',
                'catatan_perbaikan_size' => strlen($output),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Auto-resolve semua komentar PDF untuk file_type tertentu.
     * Mengembalikan jumlah yang di-resolve.
     */
    private function resolveCommentsForType(LogbookEntry $logbook, string $fileType): int
    {
        $count = $logbook->comments()
            ->fileType($fileType)
            ->whereIn('resolution_status', [PdfComment::STATUS_OPEN, PdfComment::STATUS_ADDRESSED])
            ->update([
                'resolution_status' => PdfComment::STATUS_RESOLVED,
                'is_resolved' => true,
            ]);

        return (int) $count;
    }

    /**
     * Catat pergantian lampiran/catatan di audit channel.
     */
    private function logAttachmentChange(LogbookEntry $logbook, string $field, ?string $old, ?string $new, int $resolved): void
    {
        \Illuminate\Support\Facades\Log::channel('audit')->info('Attachment replaced', [
            'entry_id' => $logbook->id,
            'field' => $field,
            'old' => $old ? basename($old) : null,
            'new' => $new ? basename($new) : null,
            'by' => auth()->id(),
            'comments_auto_resolved' => $resolved,
            'waktu' => now()->toDateTimeString(),
        ]);
    }

    // ---------------------------------------------------------------- workflow

    public function submit(LogbookEntry $logbook): RedirectResponse
    {
        $this->authorize('submit', $logbook);

        $logbook->update([
            'status' => LogbookEntry::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->bestEffort(fn () => \App\Events\EntryStatusChanged::dispatch($logbook, 'Ada entri baru menunggu review.'));
        $logbook->notifyDosen(
            'Entri '.($logbook->jenis === 'revisi' ? 'revisi' : 'logbook sesi '.$logbook->sesi_ke).' baru dikirim oleh mahasiswa.',
            route('logbook.show', $logbook),
            'Entri Baru Menunggu Review',
        );

        return back()->with('success', 'Entri dikirim ke dosen.');
    }

    public function approve(LogbookEntry $logbook): RedirectResponse
    {
        $this->authorize('review', $logbook);

        $logbook->update([
            'status' => LogbookEntry::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
        $this->resolveCommentsOnApproval($logbook);

        $this->bestEffort(fn () => \App\Events\EntryStatusChanged::dispatch($logbook, 'Entri Anda telah disetujui oleh pembimbing.'));
        $logbook->notifyParties(
            'Entri '.($logbook->jenis === 'revisi' ? 'revisi' : 'logbook sesi '.$logbook->sesi_ke).' telah disetujui.',
            route('logbook.show', $logbook),
            'Entri Disetujui',
        );

        // Evaluasi achievement mahasiswa.
        if ($owner = $logbook->mahasiswaTa?->mahasiswa) {
            app(\App\Services\AchievementService::class)->evaluateForUser($owner);
        }

        return back()->with('success', 'Entri disetujui.');
    }

    public function requestRevisi(Request $request, LogbookEntry $logbook): RedirectResponse
    {
        $this->authorize('review', $logbook);

        $validated = $request->validate([
            'feedback_dosen' => ['required', 'string', 'min:20'],
        ]);

        $logbook->update([
            'status' => LogbookEntry::STATUS_REVISI,
            'feedback_dosen' => $validated['feedback_dosen'],
            'reviewed_at' => now(),
        ]);

        $this->bestEffort(fn () => \App\Events\EntryStatusChanged::dispatch($logbook, 'Entri Anda diminta revisi: '.$validated['feedback_dosen']));
        $logbook->notifyParties(
            'Entri Anda diminta revisi: '.$validated['feedback_dosen'],
            route('logbook.show', $logbook),
            'Permintaan Revisi',
        );

        return back()->with('success', 'Entri dikembalikan untuk revisi.');
    }

    // ---------------------------------------------------------------- pdf serve

    public function pdf(LogbookEntry $logbook)
    {
        $this->authorize('view', $logbook);
        abort_if(!$logbook->lampiran_path, 404, 'File perbaikan/draft tidak tersedia.');

        return $this->inlinePdf($logbook->lampiran_path);
    }

    public function catatanPdf(LogbookEntry $logbook)
    {
        $this->authorize('view', $logbook);
        abort_if(!$logbook->catatan_perbaikan_path, 404, 'Catatan perbaikan tidak tersedia.');

        return $this->inlinePdf($logbook->catatan_perbaikan_path);
    }

    /**
     * (Opsional) Unduh PDF dengan anotasi DIBAAKAR ke dalam file (bukan overlay DOM).
     * Menggunakan FPDI: mengimpor halaman asli lalu menggambar kotak + nomor
     * komentar berdasarkan geometri Web Annotation yang tersimpan.
     */
    public function burnPdf(Request $request, LogbookEntry $logbook)
    {
        $this->authorize('view', $logbook);

        $type = $request->query('type', PdfComment::FILE_TYPE_DRAFT);
        $field = $type === PdfComment::FILE_TYPE_CATATAN ? 'catatan_perbaikan_path' : 'lampiran_path';
        abort_if(!$logbook->{$field}, 404, 'File PDF tidak tersedia.');

        $source = Storage::disk('local')->path($logbook->{$field});
        abort_unless(is_file($source), 404, 'File PDF tidak ditemukan.');

        $comments = $logbook->comments()->fileType($type)->with('user')->orderBy('page_number')->get();

        $pdf = new \setasign\Fpdi\Fpdi();
        $pageCount = $pdf->setSourceFile($source);

        foreach (range(1, $pageCount) as $pageNo) {
            $pdf->AddPage();
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

            $pageComments = $comments->where('page_number', $pageNo);
            $i = 0;
            foreach ($pageComments as $c) {
                if (!$c->isArea()) {
                    continue;
                }
                $i++;
                $x1 = $c->pos_x * $size['width'];
                $yTop = $c->pos_y * $size['height'];   // normalized top-origin
                $x2 = $c->x2 * $size['width'];
                $yBottom = $c->y2 * $size['height'];
                $w = $x2 - $x1;
                $h = $yBottom - $yTop;

                // Konversi ke koordinat PDF (origin bottom-left).
                $pdfY1 = $size['height'] - $yTop - $h;

                // Warna outline: merah untuk anotasi (tanpa isian agar tulisan tidak tertutup).
                // Tetap menampilkan label nomor + nama + isi komentar di atas area.
                $color = $c->isResolved() ? [16, 185, 129] : ($c->resolution_status === PdfComment::STATUS_ADDRESSED ? [217, 119, 6] : [220, 38, 38]);
                [$r, $g, $b] = $color;
                $pdf->SetDrawColor($r, $g, $b);
                $pdf->SetFillColor($r, $g, $b);
                $pdf->SetLineWidth(0.6);
                // 'D' = Draw outline only (tidak mengisi), agar tulisan PDF di dalamnya tetap terlihat.
                $pdf->Rect($x1, $pdfY1, $w, $h, 'D');

                // Label nomor komentar di pojok atas kotak (background solid).
                $pdf->SetFillColor($r, $g, $b);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('Helvetica', 'B', 8);
                $labelY = $pdfY1 + $h;
                $pdf->Rect($x1, $labelY, 10, 6, 'F');
                $pdf->Text($x1 + 1, $labelY + 4.5, (string) $i);

                // Teks komentar ditaruh di LUAR kotak (di atasnya) dengan background putih
                // semi-transparan, sehingga tidak menutupi tulisan PDF di dalam area.
                $name = trim((string) ($c->user?->name ?? ''));
                $text = trim((string) $c->comment);
                $lineH = 3.2;

                // Tentukan posisi: tepat di atas kotak anotasi.
                // Lebar area teks = lebar area anotasi, tetapi maksimal 120 pt.
                $textW = min(120, max(40, $w));
                $tx = $x1;
                // Posisi Y (PDF, origin bottom-left): di atas label nomor.
                $ty = $labelY + 2;

                $pdf->SetFont('Helvetica', 'B', 7);
                $pdf->SetFontSize(7);
                $pdf->SetTextColor($r, $g, $b);
                $lines = [];
                if ($name !== '') {
                    $lines[] = mb_substr($name, 0, 40);
                }
                if ($text !== '') {
                    $wrapped = $this->wrapPdfText($text, $textW, function ($s) use ($pdf) {
                        return $pdf->GetStringWidth($s);
                    });
                    foreach ($wrapped as $ln) {
                        $lines[] = $ln;
                        if (count($lines) >= 3) break;
                    }
                }
                foreach ($lines as $idx => $ln) {
                    $pdf->Text($tx, $ty + ($idx * $lineH), $ln);
                }
            }
        }

        // Halaman daftar / legend komentar di akhir dokumen.
        $this->appendCommentList($pdf, $comments, $type);

        $filename = 'anotasi-'.$logbook->mahasiswaTa?->mahasiswa?->identifier.'-'.now()->format('Ymd').'.pdf';

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Tambah halaman terakhir berisi daftar/legend seluruh komentar.
     */
    private function appendCommentList(\setasign\Fpdi\Fpdi $pdf, $comments, string $type): void
    {
        $only = $comments->filter(fn ($c) => $c->isArea())->values();

        $pdf->AddPage();
        $pdf->SetY(20);
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(0, 10, 'Daftar Anotasi ('.($type === PdfComment::FILE_TYPE_CATATAN ? 'Catatan Perbaikan' : 'File Perbaikan/Draft').')', 0, 1, 'C');

        $pdf->Ln(4);
        // Legend warna
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 7, 'Legenda:', 0, 1);
        $this->legendRow($pdf, [220, 38, 38], 'Area anotasi (outline merah)');
        $this->legendRow($pdf, [16, 185, 129], 'Sudah selesai (resolve)');

        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetFillColor(226, 232, 240);
        $pdf->Cell(12, 8, 'No', 1, 0, 'C', true);
        $pdf->Cell(18, 8, 'Hal', 1, 0, 'C', true);
        $pdf->Cell(55, 8, 'Pemberi', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Status', 1, 0, 'C', true);
        $pdf->Cell(0, 8, 'Komentar', 1, 1, 'C', true);

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetFillColor(255, 255, 255);
        $row = 0;
        foreach ($only as $idx => $c) {
            $num = $idx + 1;
            $status = $c->isResolved() ? 'Selesai' : ($c->resolution_status === PdfComment::STATUS_ADDRESSED ? 'Dijawab' : 'Terbuka');
            $statusColor = $c->isResolved() ? [16, 185, 129] : ($c->resolution_status === PdfComment::STATUS_ADDRESSED ? [217, 119, 6] : [245, 158, 11]);
            $name = trim((string) ($c->user?->name ?? '-'));
            $text = trim((string) $c->comment);

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(255, 255, 255);
            if ($row % 2 === 1) {
                $pdf->SetFillColor(248, 250, 252);
            }
            $pdf->Cell(12, 8, (string) $num, 1, 0, 'C', true);
            $pdf->Cell(18, 8, (string) $c->page_number, 1, 0, 'C', true);
            $pdf->Cell(55, 8, mb_substr($name, 0, 30), 1, 0, 'L', true);

            // status cell dengan warna
            $pdf->SetTextColor($statusColor[0], $statusColor[1], $statusColor[2]);
            $pdf->Cell(20, 8, $status, 1, 0, 'C', true);
            $pdf->SetTextColor(0, 0, 0);

            // komentar (multi-line, tinggi menyesuaikan)
            $lines = $this->wrapPdfText($text, 350, function ($s) use ($pdf) {
                return $pdf->GetStringWidth($s);
            });
            $lineH = 4.5;
            $rowH = max(8, count($lines) * $lineH);
            $x0 = $pdf->GetX();
            $y0 = $pdf->GetY();
            $pdf->Cell(0, $rowH, '', 1, 1, 'L', true);
            $pdf->SetXY($x0, $y0 + 1);
            $pdf->SetFont('Helvetica', '', 8);
            foreach ($lines as $ln) {
                $pdf->Cell(0, $lineH, $ln, 0, 1);
            }
            $pdf->SetFont('Helvetica', '', 9);

            if ($pdf->GetY() > 270) {
                $pdf->AddPage();
            }
            $row++;
        }
    }

    private function legendRow(\setasign\Fpdi\Fpdi $pdf, array $color, string $label): void
    {
        [$r, $g, $b] = $color;
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->SetFillColor($r, $g, $b);
        $pdf->SetDrawColor($r, $g, $b);
        $pdf->Rect($x, $y, 12, 6, 'DF');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x + 16, $y);
        $pdf->Cell(0, 6, $label, 0, 1);
    }

    /**
     * Bungkus teks ke beberapa baris sesuai lebar (unit PDF points).
     */
    private function wrapPdfText(string $text, float $maxWidth, callable $widthFn): array
    {
        $lines = [];
        $words = preg_split('/\s+/', trim($text));
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($widthFn($candidate) <= $maxWidth) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [''];
    }

    private function inlinePdf(string $path)
    {
        $fullPath = Storage::disk('local')->path($path);
        abort_unless(is_file($fullPath), 404, 'File PDF tidak ditemukan.');

        $size = filesize($fullPath);
        $name = basename($fullPath);

        // Streaming byte langsung + header eksplisit agar browser menampilkan
        // PDF di dalam halaman (inline), BUKAN mengunduhnya.
        return response()->streamDownload(function () use ($fullPath) {
            readfile($fullPath);
        }, $name, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => $size,
            'Cache-Control' => 'private, no-transform',
        ], 'inline');
    }

    // ---------------------------------------------------------------- viewer + comments

    public function viewer(Request $request, LogbookEntry $logbook): View
    {
        $this->authorize('view', $logbook);
        if ($request->user()->isDosen() && $request->user()->can('review', $logbook) && !$logbook->review_opened_at) {
            $logbook->update(['review_opened_at' => now()]);
        }
        $logbook->load('comments.user');

        return view('logbook.pdf-viewer', compact('logbook'));
    }

    public function comments(Request $request, LogbookEntry $logbook): JsonResponse
    {
        $this->authorize('view', $logbook);

        $type = $request->query('type', PdfComment::FILE_TYPE_DRAFT);
        $comments = $logbook->comments()
            ->fileType($type)
            ->with('user')
            ->orderBy('created_at')
            ->get()
            ->map(function (PdfComment $c) {
                // Pastikan payload W3C tersedia (untuk data lama, bangun dari kolom).
                return [
                    'id' => $c->id,
                    'user' => $c->user,
                    'file_type' => $c->file_type,
                    // Bangun ulang agar status terbaru tidak tertutup payload lama.
                    'payload' => $c->buildPayloadFromColumns(),
                    'resolution_status' => $c->resolution_status ?: ($c->is_resolved ? PdfComment::STATUS_RESOLVED : PdfComment::STATUS_OPEN),
                    'created_at' => $c->created_at,
                ];
            });

        return response()->json($comments);
    }

    /**
     * Simpan anotasi PDF. Mendukung dua bentuk input:
     *  a) W3C Web Annotation JSON pada kolom `payload` (arsitektur baru), atau
     *  b) kolom flat lama (file_type, page_number, pos_x, pos_y, x2, y2, comment).
     * Geometri kolom tetap disinkronkan dari payload untuk render cepat.
     */
    public function storeComment(Request $request, LogbookEntry $logbook): JsonResponse
    {
        $this->authorize('view', $logbook);

        $request->validate([
            'file_type' => ['required', 'in:'.implode(',', PdfComment::FILE_TYPES)],
            'payload' => ['sometimes', 'array'],
            'comment' => ['required_without:payload', 'string'],
        ]);

        $fileType = $request->input('file_type');
        $payload = $request->input('payload');

        $comment = new PdfComment([
            'user_id' => $request->user()->id,
            'file_type' => $fileType,
        ]);

        if (is_array($payload)) {
            // Arsitektur baru: simpan payload Web Annotation, sinkronkan geometri.
            $comment->payload = $payload;
            $comment->syncFromPayload();
        } else {
            // Fallback kolom flat lama.
            $comment->page_number = $request->integer('page_number');
            $comment->pos_x = $request->input('pos_x');
            $comment->pos_y = $request->input('pos_y');
            $comment->x2 = $request->input('x2');
            $comment->y2 = $request->input('y2');
            $comment->comment = $request->input('comment');
            $comment->is_resolved = false;
            $comment->resolution_status = PdfComment::STATUS_OPEN;
        }

        $logbook->comments()->save($comment);

        $this->bestEffort(fn () => \App\Events\PdfCommentCreated::dispatch($comment));

        // Notifikasi ke pihak terkait (kecuali penulis komentar sendiri).
        $recipients = [];
        if ($ownerId = $logbook->mahasiswaTa?->user_id) {
            $recipients[] = $ownerId;
        }
        if ($dosen = $logbook->reviewDosen()) {
            $recipients[] = $dosen->id;
        }
        foreach (array_unique(array_filter($recipients)) as $id) {
            if ($id !== $request->user()->id && ($u = \App\Models\User::find($id))) {
                $this->bestEffort(fn () => $u->notify(new \App\Notifications\ActivityNotification(
                    'Komentar baru pada PDF entri Anda: '.$comment->comment,
                    route('logbook.show', $logbook),
                    'Komentar PDF Baru',
                )));
            }
        }

        return response()->json([
            'id' => $comment->id,
            'user' => $comment->user,
            'file_type' => $comment->file_type,
            'payload' => $comment->payload,
            'resolution_status' => $comment->resolution_status,
            'created_at' => $comment->created_at,
        ], 201);
    }

    /** Resolve current and parent annotations when a review is approved. */
    private function resolveCommentsOnApproval(LogbookEntry $logbook): void
    {
        $entries = collect([$logbook, $logbook->parentEntry])->filter();

        foreach ($entries as $entry) {
            $entry->comments()
                ->where('resolution_status', '!=', PdfComment::STATUS_RESOLVED)
                ->get()
                ->each(function (PdfComment $comment) {
                    $comment->setResolutionStatus(PdfComment::STATUS_RESOLVED);
                    $comment->save();
                });
        }
    }
}
