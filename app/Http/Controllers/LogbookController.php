<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLogbookEntryRequest;
use App\Http\Requests\StoreRevisiRequest;
use App\Http\Requests\UpdateLogbookEntryRequest;
use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\PdfComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LogbookController extends Controller
{
    // ---------------------------------------------------------------- create

    public function create(Request $request): View
    {
        $ta = $request->user()->mahasiswaTa;
        abort_unless($ta, 403, 'Anda belum memiliki data TA.');

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
        $ta = $request->user()->mahasiswaTa;
        abort_unless($ta, 403, 'Anda belum memiliki data TA.');

        return view('logbook.create-revisi', compact('ta'));
    }

    public function store(StoreLogbookEntryRequest $request): RedirectResponse
    {
        $ta = $request->user()->mahasiswaTa;
        abort_unless($ta, 403);

        $data = $request->validated();

        // Tombol "Kirim ke Pembimbing" langsung mengirim (bukan draf).
        $submit = $request->boolean('submit');
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
            $entry->update([
                'lampiran_path' => $this->storeUniqueFile($request->file('lampiran'), 'lampiran', $entry->id),
                'lampiran_original_name' => $request->file('lampiran')->getClientOriginalName(),
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
                ? 'Entri logbook dikirim ke pembimbing.'
                : 'Entri logbook tersimpan sebagai draf.');
    }

    public function storeRevisi(StoreRevisiRequest $request): RedirectResponse
    {
        $ta = $request->user()->mahasiswaTa;
        abort_unless($ta, 403);

        $data = $request->validated();
        $submit = $request->boolean('submit');

        // Buat entry dulu agar path unik bisa memakai id.
        $entry = $ta->entries()->create([
            'sesi_ke' => 0,
            'jenis' => LogbookEntry::JENIS_REVISI,
            'progres_kendala' => $data['progres_kendala'],
            'tanggal_pengiriman' => $data['tanggal_pengiriman'],
            'status' => $submit ? LogbookEntry::STATUS_SUBMITTED : LogbookEntry::STATUS_DRAFT,
            'submitted_at' => $submit ? now() : null,
        ]);

        $entry->update([
            'lampiran_path' => $this->storeUniqueFile($request->file('lampiran'), 'lampiran', $entry->id),
            'lampiran_original_name' => $request->file('lampiran')->getClientOriginalName(),
            'catatan_perbaikan_path' => $this->storeUniqueFile($request->file('catatan_perbaikan'), 'catatan', $entry->id),
            'catatan_original_name' => $request->file('catatan_perbaikan')->getClientOriginalName(),
        ]);

        if ($submit) {
            $this->bestEffort(fn () => \App\Events\EntryStatusChanged::dispatch($entry, 'Ada entri revisi baru menunggu review.'));
            $entry->notifyDosen(
                'Entri revisi baru dikirim oleh mahasiswa.',
                route('logbook.show', $entry),
                'Entri Baru Menunggu Review',
            );
        }

        return redirect()->route('logbook.index')
            ->with('success', $submit
                ? 'Entri revisi dikirim ke pembimbing.'
                : 'Entri revisi tersimpan sebagai draf.');
    }

    // ---------------------------------------------------------------- index

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['status', 'jenis', 'date_from', 'date_to', 'keyword']);

        if ($user->isMahasiswa()) {
            $ta = $user->mahasiswaTa;
            $query = $ta
                ? $ta->entries()->with('comments')
                : LogbookEntry::query()->whereRaw('1 = 0');
        } elseif ($user->isDosen()) {
            // Semua entry dari TA yang dibimbing dosen ini.
            $taIds = MahasiswaTa::where('pembimbing_1_id', $user->id)
                ->orWhere('pembimbing_2_id', $user->id)
                ->pluck('id');
            $query = LogbookEntry::whereIn('mahasiswa_ta_id', $taIds)
                ->orWhere('dosen_id', $user->id)
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

    // ---------------------------------------------------------------- show

    public function show(Request $request, LogbookEntry $logbook): View
    {
        $this->authorize('view', $logbook);
        $logbook->load(['mahasiswaTa.mahasiswa', 'mahasiswaTa.pembimbing1', 'mahasiswaTa.pembimbing2', 'dosen', 'comments.user']);

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
            ]);
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
        $logbook->update(['lampiran_path' => null, 'lampiran_original_name' => null]);
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
        $logbook->update(['catatan_perbaikan_path' => null, 'catatan_original_name' => null]);
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
     * Auto-resolve semua komentar PDF untuk file_type tertentu.
     * Mengembalikan jumlah yang di-resolve.
     */
    private function resolveCommentsForType(LogbookEntry $logbook, string $fileType): int
    {
        $count = $logbook->comments()
            ->fileType($fileType)
            ->where('is_resolved', false)
            ->update(['is_resolved' => true]);

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

        return back()->with('success', 'Entri dikirim ke pembimbing.');
    }

    public function approve(LogbookEntry $logbook): RedirectResponse
    {
        $this->authorize('review', $logbook);

        $logbook->update([
            'status' => LogbookEntry::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

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
            'feedback_dosen' => ['required', 'string'],
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

                // Warna: amber = belum resolve, hijau = sudah resolve.
                $color = $c->is_resolved ? [16, 185, 129] : [245, 158, 11];
                [$r, $g, $b] = $color;
                $pdf->SetDrawColor($r, $g, $b);
                $pdf->SetFillColor($r, $g, $b);
                $pdf->SetLineWidth(0.8);
                $pdf->Rect($x1, $pdfY1, $w, $h, 'DF');

                // Label nomor komentar di pojok atas kotak.
                $pdf->SetFillColor($r, $g, $b);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('Helvetica', 'B', 8);
                $labelY = $pdfY1 + $h;
                $pdf->Rect($x1, $labelY, 10, 6, 'F');
                $pdf->Text($x1 + 1, $labelY + 4.5, (string) $i);

                // Nama pemberi komentar + isi komentar di dalam kotak.
                $name = trim((string) ($c->user?->name ?? ''));
                $text = trim((string) $c->comment);
                $maxW = $w - 12;
                $lineH = 3;
                $tx = $x1 + 4;
                // Mulai dari atas kotak (koordinat PDF, descending).
                $ty = $pdfY1 + $h - 8;
                $maxLines = max(1, (int) floor(($h - 10) / $lineH));

                $pdf->SetFont('Helvetica', 'B', 7);
                $pdf->SetFontSize(7);
                $pdf->SetTextColor($r, $g, $b);
                if ($name !== '') {
                    $pdf->Text($tx, $ty, mb_substr($name, 0, 30));
                    $ty -= $lineH;
                    $maxLines--;
                }

                $pdf->SetFont('Helvetica', '', 7);
                $pdf->SetFontSize(7);
                if ($text !== '' && $maxW > 20 && $maxLines > 0) {
                    $lines = $this->wrapPdfText($text, $maxW, function ($s) use ($pdf) {
                        return $pdf->GetStringWidth($s);
                    });
                    foreach (array_slice($lines, 0, $maxLines) as $idx => $ln) {
                        $pdf->Text($tx, $ty - ($idx * $lineH), $ln);
                    }
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
        $this->legendRow($pdf, [245, 158, 11], 'Belum selesai (belum resolve)');
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
            $status = $c->is_resolved ? 'Selesai' : 'Belum';
            $statusColor = $c->is_resolved ? [16, 185, 129] : [245, 158, 11];
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
                    'payload' => $c->payload ?? $c->buildPayloadFromColumns(),
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
            'created_at' => $comment->created_at,
        ], 201);
    }
}
