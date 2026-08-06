<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class MahasiswaTa extends Model
{
    use HasFactory;

    protected $table = 'mahasiswa_ta';

    /**
     * Mode institusi: batasi query ke institusi aktif (tenant scope).
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope());
    }

    /** Jenis program mahasiswa. */
    public const JENIS_TA = 'ta';
    public const JENIS_KP = 'kp';
    public const JENISES = [self::JENIS_TA, self::JENIS_KP];

    /** Fase perjalanan TA. */
    public const FASES = [
        'proposal' => 'Seminar Proposal',
        'pengumpulan_data' => 'Pengumpulan Data',
        'analisis' => 'Analisis',
        'seminar_hasil' => 'Seminar Hasil',
        'draft_sidang' => 'Draft Sidang',
        'sidang' => 'Sidang',
        'achievement' => 'Achievement Unlocked',
    ];

    /** Fase perjalanan KP. */
    public const FASES_KP = [
        'pelaksanaan' => 'Pelaksanaan',
        'laporan' => 'Penyusunan Laporan',
        'seminar_kp' => 'Seminar KP',
        'selesai' => 'Selesai',
    ];

    /** Status siklus TA. */
    public const STATUS_AKTIF = 'aktif';
    public const STATUS_TAMAT = 'tamat';
    public const STATUS_NONAKTIF = 'nonaktif';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_DITOLAK = 'ditolak';
    public const STATUS_TA = [
        self::STATUS_AKTIF,
        self::STATUS_TAMAT,
        self::STATUS_NONAKTIF,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_DITOLAK,
    ];

    protected $fillable = [
        'institution_id',
        'user_id',
        'jenis',
        'judul_ta',
        'tempat_kp',
        'alamat_perusahaan',
        'jenis_instansi',
        'profil_perusahaan',
        'pembimbing_1_id',
        'pembimbing_2_id',
        'pembimbing_lapangan',
        'penguji_1_id',
        'penguji_2_id',
        'target_sesi',
        'periode_mulai',
        'periode_selesai',
        'fase',
        'status_ta',
        'alasan_ditolak',
    ];

    protected function casts(): array
    {
        return [
            'target_sesi' => 'integer',
            'periode_mulai' => 'date',
            'periode_selesai' => 'date',
        ];
    }

    public function isTa(): bool
    {
        return $this->jenis === self::JENIS_TA;
    }

    public function isKp(): bool
    {
        return $this->jenis === self::JENIS_KP;
    }

    /**
     * Label program: "TA" atau "KP".
     */
    public function jenisLabel(): string
    {
        return $this->isKp() ? 'KP' : 'TA';
    }

    public function faseLabel(): string
    {
        $fases = $this->isKp() ? self::FASES_KP : self::FASES;
        return $fases[$this->fase] ?? $this->fase;
    }

    public function faseIndex(): int
    {
        $keys = $this->isKp() ? array_keys(self::FASES_KP) : array_keys(self::FASES);
        $i = array_search($this->fase, $keys, true);
        return $i === false ? 0 : $i;
    }

    /**
     * Scope: filter program berdasarkan jenis (ta/kp).
     */
    public function scopeJenis($q, string $jenis)
    {
        return $q->where('jenis', $jenis);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Anggota tambahan kelompok (khusus KP). Pemilik utama tetap di user_id.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mahasiswa_ta_members', 'mahasiswa_ta_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Apakah user adalah anggota program ini (pemilik utama ATAU anggota pivot).
     */
    public function isMember(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Semua anggota program (pemilik utama + anggota pivot), tanpa duplikasi.
     */
    public function allMembers()
    {
        $members = $this->members()->get();

        if ($this->mahasiswa && !$members->contains('id', $this->user_id)) {
            $members = $members->prepend($this->mahasiswa);
        }

        return $members->values();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function pembimbing1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembimbing_1_id');
    }

    public function pembimbing2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembimbing_2_id');
    }

    public function penguji1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penguji_1_id');
    }

    public function penguji2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penguji_2_id');
    }

    /**
     * Both pembimbings (non-null), in order.
     */
    public function pembimbings()
    {
        return $this->pembimbing1 ?? $this->pembimbing2;
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LogbookEntry::class, 'mahasiswa_ta_id');
    }

    /**
     * Logbook harian KP (kegiatan lapangan).
     */
    public function logbookHarian(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\LogbookHarianKp::class, 'mahasiswa_ta_id');
    }

    public function inactivityNotifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\InactivityNotification::class, 'mahasiswa_ta_id');
    }

    public function workspaceFiles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\WorkspaceFile::class, 'mahasiswa_ta_id');
    }

    public function sidangs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Sidang::class, 'mahasiswa_ta_id');
    }

    public function seminarSubmissions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\SeminarSubmission::class, 'mahasiswa_ta_id');
    }

    public function finalization(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\ThesisFinalization::class);
    }

    /**
     * Scope: TA di mana user adalah pembimbing (P1/P2) ATAU penguji.
     */
    public function scopeBimbinganOleh($q, User $user)
    {
        return $q->where(function ($w) use ($user) {
            $w->where('pembimbing_1_id', $user->id)
                ->orWhere('pembimbing_2_id', $user->id)
                ->orWhere('penguji_1_id', $user->id)
                ->orWhere('penguji_2_id', $user->id);
        });
    }

    public function scopeAktif($q)
    {
        return $q->where('status_ta', self::STATUS_AKTIF);
    }

    public function scopeTamat($q)
    {
        return $q->where('status_ta', self::STATUS_TAMAT);
    }

    /**
     * Data regularity (di-cache 6 jam) — satu sumber untuk status & tooltip.
     * Status health bimbingan:
     *   - green  : selisih hari sejak bimbingan terakhir < 15 hari
     *   - yellow : 15 <= selisih <= 40 hari
     *   - red    : selisih > 40 hari (atau belum pernah bimbingan)
     */
    public function regularityData(): array
    {
        return Cache::remember(
            "regularity2:{$this->id}",
            now()->addHours(6),
            fn () => $this->computeRegularity()
        );
    }

    public function getRegularityStatusAttribute(): string
    {
        return $this->regularityData()['status'];
    }

    /**
     * Tooltip: "Terakhir bimbingan X hari lalu (biasanya tiap Y hari)".
     */
    public function getRegularityTooltipAttribute(): string
    {
        $data = $this->regularityData();

        if ($data['days_since'] === null) {
            return 'Belum pernah bimbingan';
        }

        return "Terakhir bimbingan {$data['days_since']} hari lalu (biasanya tiap {$data['avg_interval']} hari)";
    }

    /**
     * Hitung status + data regularity.
     */
    public function computeRegularity(): array
    {
        $now = now();

        $lastEntry = $this->entries()
            ->whereNotNull('tanggal_bimbingan')
            ->orderByDesc('tanggal_bimbingan')
            ->first();

        if (!$lastEntry) {
            return ['status' => 'red', 'days_since' => null, 'avg_interval' => null];
        }

        // Guard: tanggal di masa depan (data lama/impor) dianggap "bimbingan hari ini".
        $daysSinceLast = (int) max(0, $lastEntry->tanggal_bimbingan->startOfDay()
            ->diffInDays($now->copy()->startOfDay()));

        $dates = $this->entries()
            ->whereNotNull('tanggal_bimbingan')
            ->orderByDesc('tanggal_bimbingan')
            ->limit(5)
            ->pluck('tanggal_bimbingan');

        $avgInterval = 14; // asumsi 2 minggu untuk mahasiswa baru
        if ($dates->count() > 1) {
            // diffInDays bisa negatif tergantung urutan (first lebih baru).
            $span = abs($dates->first()->diffInDays($dates->last()));
            $avgInterval = $span / ($dates->count() - 1);
            if ($avgInterval < 1) {
                $avgInterval = 1;
            }
        }

        if ($daysSinceLast < 15) {
            $status = 'green';
        } elseif ($daysSinceLast <= 40) {
            $status = 'yellow';
        } else {
            $status = 'red';
        }

        return [
            'status' => $status,
            'days_since' => $daysSinceLast,
            'avg_interval' => (int) round($avgInterval),
        ];
    }

    /**
     * Apakah mahasiswa ini sudah dikirimi email inaktivitas (ikon ⚠).
     */
    public function wasWarnedInactive(): bool
    {
        return $this->inactivityNotifications()->exists();
    }

    /**
     * A dosen is a pembimbing of this TA when they are pembimbing_1,
     * pembimbing_2 or both.
     */
    public function isPembimbing(User $dosen): bool
    {
        return $this->pembimbing_1_id === $dosen->id
            || $this->pembimbing_2_id === $dosen->id;
    }

    /**
     * A dosen is a penguji of this TA when they are penguji_1 or penguji_2.
     */
    public function isPenguji(User $dosen): bool
    {
        return $this->penguji_1_id === $dosen->id
            || $this->penguji_2_id === $dosen->id;
    }

    /**
     * Semua dosen terkait TA ini (pembimbing + penguji), tanpa duplikasi.
     */
    public function allDosenIds(): array
    {
        return array_values(array_unique(array_filter([
            $this->pembimbing_1_id,
            $this->pembimbing_2_id,
            $this->penguji_1_id,
            $this->penguji_2_id,
        ])));
    }
}