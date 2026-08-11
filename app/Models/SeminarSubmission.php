<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeminarSubmission extends Model
{
    use HasFactory;

    public const JENIS_PROPOSAL = 'seminar_proposal';
    public const JENIS_SEMINAR_HASIL = 'seminar_hasil';
    public const JENIS_SIDANG = 'sidang_akhir';
    public const JENIS_SEMINAR_KP = 'seminar_kp';
    public const JENISES = [
        self::JENIS_PROPOSAL,
        self::JENIS_SEMINAR_HASIL,
        self::JENIS_SIDANG,
        self::JENIS_SEMINAR_KP,
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_SUBMITTED];

    protected $fillable = [
        'mahasiswa_ta_id',
        'jenis',
        'tanggal',
        'waktu',
        'lokasi',
        'undangan_path',
        'undangan_original_name',
        'undangan_sebagai',
        'materi_path',
        'materi_original_name',
        'materi_workspace_file_id',
        'catatan_hardcopy',
        'catatan_keterangan',
        'status',
        'sidang_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'waktu' => 'datetime:H:i',
        ];
    }

    public function mahasiswaTa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaTa::class, 'mahasiswa_ta_id');
    }

    public function workspaceFile(): BelongsTo
    {
        return $this->belongsTo(WorkspaceFile::class, 'materi_workspace_file_id');
    }

    public function sidang(): BelongsTo
    {
        return $this->belongsTo(Sidang::class, 'sidang_id');
    }

    public function reads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SeminarSubmissionRead::class, 'seminar_submission_id');
    }

    /**
     * Apakah dosen tertentu sudah membaca submission ini.
     */
    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }

    /**
     * Tandai submission ini sudah dibaca oleh dosen tertentu.
     */
    public function markReadBy(User $user): void
    {
        $this->reads()->updateOrCreate(
            ['user_id' => $user->id],
            ['read_at' => now()]
        );
    }

    public function jenisLabel(): string
    {
        // Label fase kustom dari prodi/departemen (jika ada).
        if ($this->mahasiswaTa) {
            $labels = app(\App\Services\ProgramNamingService::class)->faseLabels($this->mahasiswaTa);

            $faseKey = match ($this->jenis) {
                self::JENIS_PROPOSAL => 'proposal',
                self::JENIS_SEMINAR_HASIL => 'seminar_hasil',
                self::JENIS_SIDANG => 'sidang',
                self::JENIS_SEMINAR_KP => 'seminar_kp',
                default => null,
            };

            if ($faseKey && isset($labels[$faseKey])) {
                return $labels[$faseKey];
            }
        }

        return self::staticLabel($this->jenis);
    }

    /**
     * Jenis submission yang sesuai dengan fase aktif program (proposal →
     * seminar_proposal, seminar_hasil → seminar_hasil, sidang → sidang_akhir,
     * seminar_kp → seminar_kp). Satu sumber agar controller & dashboard selaras.
     */
    public static function jenisFromFase(\App\Models\MahasiswaTa $ta): string
    {
        return match ($ta->fase) {
            'proposal' => self::JENIS_PROPOSAL,
            'seminar_hasil' => self::JENIS_SEMINAR_HASIL,
            'sidang' => self::JENIS_SIDANG,
            'seminar_kp' => self::JENIS_SEMINAR_KP,
            default => $ta->isKp() ? self::JENIS_SEMINAR_KP : self::JENIS_PROPOSAL,
        };
    }

    /**
     * Apakah mahasiswa masih boleh memperbarui dokumen submission ini.
     * Boleh selama belum dikonversi ke riwayat sidang DAN jadwal belum lewat.
     */
    public function isUpdatableByStudent(): bool
    {
        return $this->sidang_id === null
            && $this->tanggal !== null
            && $this->tanggal->startOfDay()->gte(now()->startOfDay());
    }

    /**
     * Label statis sebuah jenis submission (default di luar penamaan kustom prodi).
     */
    public static function staticLabel(string $jenis): string
    {
        return match ($jenis) {
            self::JENIS_PROPOSAL => 'Seminar Proposal',
            self::JENIS_SEMINAR_HASIL => 'Seminar Hasil',
            self::JENIS_SIDANG => 'Sidang Akhir',
            self::JENIS_SEMINAR_KP => 'Seminar KP',
            default => ucfirst(str_replace('_', ' ', $jenis)),
        };
    }

    public function undanganSebagaiLabel(): string
    {
        return match ($this->undangan_sebagai) {
            'pembimbing_1' => 'Pembimbing 1',
            'pembimbing_2' => 'Pembimbing 2',
            'penguji_1' => 'Penguji 1',
            'penguji_2' => 'Penguji 2',
            default => ucfirst(str_replace('_', ' ', $this->undangan_sebagai)),
        };
    }

    public function statusLabel(): string
    {
        return $this->status === self::STATUS_SUBMITTED ? 'Dikirim' : 'Draf';
    }

    /**
     * Materi dipilih dari file workspace (bukan upload baru).
     */
    public function materiFromWorkspace(): bool
    {
        return $this->materi_workspace_file_id !== null;
    }
}