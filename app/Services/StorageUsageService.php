<?php

namespace App\Services;

use App\Models\LogbookEntry;
use App\Models\LogbookHarianKp;
use App\Models\MahasiswaTa;
use App\Models\SeminarSubmission;
use App\Models\ThesisFinalization;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Support\Feature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Menghitung pemakaian penyimpanan (bytes) untuk seorang user.
 *
 * Untuk dosen: total dari workspace pribadi dosen + seluruh file & lampiran
 * dari mahasiswa yang dibimbingnya (logbook, revisi, workspace mahasiswa,
 * seminar-materials, finalization, logbook harian KP).
 * Untuk mahasiswa: total dari workspace & lampiran miliknya.
 *
 * Quota dibebankan ke dosen (pembimbing 1, fallback pembimbing 2).
 */
class StorageUsageService
{
    /**
     * Total pemakaian (bytes) untuk user.
     */
    public function totalBytes(User $user): int
    {
        if ($user->isDosen()) {
            return $this->dosenBytes($user);
        }

        return $this->mahasiswaBytes($user);
    }

    /**
     * Pemakaian dosen = workspace pribadi + data mahasiswa bimbingan.
     */
    private function dosenBytes(User $dosen): int
    {
        $total = 0;

        // 1. Workspace pribadi dosen.
        $total += WorkspaceFile::where('user_id', $dosen->id)->sum('size');

        // 1b. Foto profil dosen sendiri.
        $total += $this->diskSize('public', $dosen->profile_photo_path);

        // 2. Program (TA/KP) yang dibebani ke dosen ini.
        //    Pembimbing 1 (valid) -> fallback pembimbing 2. Penguji tidak dihitung.
        $programIds = $this->dosenProgramIds($dosen);

        // 3. Workspace mahasiswa dari program yang dibebani.
        $total += WorkspaceFile::whereIn('mahasiswa_ta_id', $programIds)->sum('size');

        // 3b. Foto profil mahasiswa dari program yang dibebani.
        $total += $this->mahasiswaProfilePhotoBytes($programIds);

        // 4. Lampiran logbook & revisi dari program yang dibebani.
        $total += LogbookEntry::whereIn('mahasiswa_ta_id', $programIds)
            ->whereNotNull('lampiran_path')
            ->sum('lampiran_size');

        // 4b. Catatan perbaikan (PDF auto-generate) dari program yang dibebani.
        $total += LogbookEntry::whereIn('mahasiswa_ta_id', $programIds)
            ->whereNotNull('catatan_perbaikan_path')
            ->sum('catatan_perbaikan_size');

        // 5. Foto logbook harian KP dari program yang dibebani.
        $total += $this->logbookHarianKpBytes($programIds);

        // 6. File seminar-materials dari program yang dibebani.
        $total += $this->seminarBytes($programIds);

        // 7. File finalization dari program yang dibebani.
        $total += $this->finalizationBytes($programIds);

        return (int) $total;
    }

    /**
     * Pemakaian mahasiswa = workspace + lampiran miliknya.
     */
    private function mahasiswaBytes(User $mahasiswa): int
    {
        $total = 0;

        $taIds = $mahasiswa->mahasiswaPrograms()->pluck('id');

        $total += WorkspaceFile::whereIn('mahasiswa_ta_id', $taIds)->sum('size');

        // Foto profil mahasiswa sendiri.
        $total += $this->diskSize('public', $mahasiswa->profile_photo_path);

        $total += LogbookEntry::whereIn('mahasiswa_ta_id', $taIds)
            ->whereNotNull('lampiran_path')
            ->sum('lampiran_size');

        // Catatan perbaikan (PDF auto-generate).
        $total += LogbookEntry::whereIn('mahasiswa_ta_id', $taIds)
            ->whereNotNull('catatan_perbaikan_path')
            ->sum('catatan_perbaikan_size');

        // Foto logbook harian KP.
        $total += $this->logbookHarianKpBytes($taIds);

        // File seminar-materials.
        $total += $this->seminarBytes($taIds);

        // File finalization.
        $total += $this->finalizationBytes($taIds);

        return (int) $total;
    }

    /**
     * ID program (TA/KP) yang dibebani ke dosen tertentu.
     * Pembimbing 1 (valid) -> fallback pembimbing 2. Penguji tidak dihitung.
     */
    public function dosenProgramIds(User $dosen): \Illuminate\Support\Collection
    {
        return MahasiswaTa::where(function ($q) use ($dosen) {
            $q->where('pembimbing_1_id', $dosen->id)
              ->whereHas('pembimbing1', fn ($u) => $u->whereIn('registration_status', ['active', 'approved']))
              ->orWhere(function ($q2) use ($dosen) {
                  $q2->where('pembimbing_2_id', $dosen->id)
                     ->where(function ($q3) {
                         $q3->whereNull('pembimbing_1_id')
                            ->orWhereDoesntHave('pembimbing1', fn ($u) => $u->whereIn('registration_status', ['active', 'approved']));
                     });
              });
            })
            // Dosen valid = registration_status 'active' (self-register) atau 'approved'
            // (dosen demo/seeded). Sebelumnya hanya 'approved' sehingga dosen yang
            // mendaftar lewat aplikasi (status 'active') tidak pernah dibebani
            // penyimpanan mahasiswa bimbingannya.
            // Hanya program yang sudah DISETUJUI yang dibebankan ke dosen.
            // Program yang masih pending/ditolak dibebankan ke mahasiswa (kuota 100 MB),
            // jadi file-nya tidak boleh ikut terhitung di kuota dosen sebelum disetujui.
            ->whereNotIn('status_ta', [MahasiswaTa::STATUS_PENDING_APPROVAL, MahasiswaTa::STATUS_DITOLAK])
            ->pluck('id');
    }

    /**
     * Total ukuran foto profil mahasiswa (pemilik utama) dari sekumpulan program.
     */
    private function mahasiswaProfilePhotoBytes($programIds): int
    {
        if ($programIds->isEmpty()) {
            return 0;
        }

        $total = 0;
        $paths = MahasiswaTa::whereIn('id', $programIds)
            ->with('mahasiswa:id,profile_photo_path')
            ->get()
            ->pluck('mahasiswa.profile_photo_path')
            ->filter();

        foreach ($paths as $path) {
            $total += $this->diskSize('public', $path);
        }

        return (int) $total;
    }

    private function logbookHarianKpBytes($programIds): int
    {
        if ($programIds->isEmpty()) {
            return 0;
        }

        $total = 0;
        $entries = LogbookHarianKp::whereIn('mahasiswa_ta_id', $programIds)
            ->where(fn ($q) => $q->whereNotNull('foto_1')->orWhereNotNull('foto_2'))
            ->get(['foto_1', 'foto_2']);

        foreach ($entries as $e) {
            foreach (['foto_1', 'foto_2'] as $col) {
                if ($e->{$col}) {
                    $total += $this->diskSize('local', $e->{$col});
                }
            }
        }

        return (int) $total;
    }

    private function seminarBytes($programIds): int
    {
        if ($programIds->isEmpty()) {
            return 0;
        }

        $total = 0;
        $submissions = SeminarSubmission::whereIn('mahasiswa_ta_id', $programIds)
            ->get(['undangan_path', 'materi_path']);

        foreach ($submissions as $s) {
            if ($s->undangan_path) {
                $total += $this->diskSize('local', $s->undangan_path);
            }
            if ($s->materi_path) {
                $total += $this->diskSize('local', $s->materi_path);
            }
        }

        return (int) $total;
    }

    private function finalizationBytes($programIds): int
    {
        if ($programIds->isEmpty()) {
            return 0;
        }

        $total = 0;
        $finalizations = ThesisFinalization::whereIn('mahasiswa_ta_id', $programIds)
            ->get(['cover_path', 'pengesahan_path', 'full_file_path']);

        foreach ($finalizations as $f) {
            foreach (['cover_path', 'pengesahan_path', 'full_file_path'] as $col) {
                if ($f->{$col}) {
                    $total += $this->diskSize('local', $f->{$col});
                }
            }
        }

        return (int) $total;
    }

    /**
     * Ukuran file di disk tertentu (bytes), aman jika file tidak ada.
     */
    private function diskSize(string $disk, ?string $path): int
    {
        if (!$path) {
            return 0;
        }

        try {
            return (int) Storage::disk($disk)->size($path);
        } catch (\Throwable $ex) {
            return 0;
        }
    }

    /**
     * Bungkus cek kuota + penyimpanan file secara atomik agar terhindar dari
     * race condition (dua upload paralel yang sama-sama lolos cek kuota).
     * Lock di-key per-institusi untuk user institusi (shared pool dipakai
     * bersama semua user institusi itu), atau per-user untuk user personal.
     *
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function withUploadLock(User $chargedUser, int $incomingBytes, \Closure $callback): mixed
    {
        $lockKey = $chargedUser->institution_id
            ? 'storage-quota:inst:'.$chargedUser->institution_id
            : 'storage-quota:user:'.$chargedUser->id;

        return Cache::lock($lockKey, 15)->block(5, function () use ($chargedUser, $incomingBytes, $callback) {
            $this->assertCanUpload($chargedUser, $incomingBytes);

            return $callback();
        });
    }

    /**
     * Cek apakah user (dosen) masih punya sisa kuota untuk upload sebesar $incomingBytes.
     * limit = 0 artinya unlimited (tidak dibatasi).
     *
     * Untuk user institusi: cek shared pool institusi (total semua user institusi).
     * Untuk user personal: cek kuota individual.
     */
    public function assertCanUpload(User $dosen, int $incomingBytes): void
    {
        // Mahasiswa hanya menjadi target kuota pada fase PENDING (program menunggu
        // persetujuan dosen, lihat MahasiswaTa::storageChargeTarget). Pakai kuota
        // sementara 100 MB sampai program disetujui.
        if ($dosen->isMahasiswa()) {
            $this->assertPendingStudentCanUpload($dosen, $incomingBytes);
            return;
        }

        // User institusi: cek shared pool institusi.
        if ($dosen->institution_id) {
            $poolMb = Feature::institutionStorageLimitMb($dosen->institution_id);
            if ($poolMb <= 0) {
                // Institusi tidak punya langganan — fallback ke kuota individual.
                $this->assertIndividualCanUpload($dosen, $incomingBytes);
                return;
            }

            $poolBytes = $poolMb * 1048576;
            $poolUsed = Feature::institutionStorageUsedMb($dosen->institution_id) * 1048576;

            if ($poolUsed + $incomingBytes > $poolBytes) {
                $remaining = max(0, $poolBytes - $poolUsed);
                abort(422, 'Kuota penyimpanan institusi tidak mencukupi. Terpakai '.$this->formatBytes($poolUsed)
                    .' dari '.$this->formatBytes($poolBytes)
                    .' (sisa '.$this->formatBytes($remaining).').');
            }

            // Juga cek batas per-user (jika diatur).
            $perUserMb = $dosen->institution_storage_limit_mb;
            if ($perUserMb !== null && $perUserMb > 0) {
                $perUserBytes = $perUserMb * 1048576;
                $used = $this->totalBytes($dosen);
                if ($used + $incomingBytes > $perUserBytes) {
                    $remaining = max(0, $perUserBytes - $used);
                    abort(422, 'Kuota penyimpanan per-user tidak mencukupi. Terpakai '.$this->formatBytes($used)
                        .' dari '.$this->formatBytes($perUserBytes)
                        .' (sisa '.$this->formatBytes($remaining).').');
                }
            }

            return;
        }

        // User personal: cek kuota individual.
        $this->assertIndividualCanUpload($dosen, $incomingBytes);
    }

    /**
     * Cek kuota individual (plan + addon) untuk user personal.
     */
    private function assertIndividualCanUpload(User $dosen, int $incomingBytes): void
    {
        $limitMb = Feature::storageLimitMb($dosen);
        if ($limitMb <= 0) {
            return; // unlimited
        }

        $limitBytes = $limitMb * 1048576;
        $used = $this->totalBytes($dosen);

        if ($used + $incomingBytes > $limitBytes) {
            $remaining = max(0, $limitBytes - $used);
            abort(422, 'Kuota penyimpanan tidak mencukupi. Terpakai '.$this->formatBytes($used)
                .' dari '.$this->formatBytes($limitBytes)
                .' (sisa '.$this->formatBytes($remaining).').');
        }
    }

    /**
     * Cek kuota sementara 100 MB untuk mahasiswa yang programnya masih
     * menunggu persetujuan dosen (fase pending).
     */
    private function assertPendingStudentCanUpload(User $mahasiswa, int $incomingBytes): void
    {
        $limitMb = Feature::pendingStudentStorageLimitMb();
        $limitBytes = $limitMb * 1048576;
        $used = $this->totalBytes($mahasiswa);

        if ($used + $incomingBytes > $limitBytes) {
            $remaining = max(0, $limitBytes - $used);
            abort(422, 'Kuota penyimpanan sementara tidak cukup (menunggu persetujuan dosen). Terpakai '
                .$this->formatBytes($used).' dari '.$this->formatBytes($limitBytes)
                .' (sisa '.$this->formatBytes($remaining).'). Maksimal '.$limitMb.' MB sampai dosen menyetujui program Anda.');
        }
    }

    /**
     * Format bytes menjadi string ramah-baca (mis. "3.2 GB").
     */
    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 1).' '.$units[$i];
    }
}