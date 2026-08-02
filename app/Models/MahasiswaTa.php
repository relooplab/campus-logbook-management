<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** Status siklus TA. */
    public const STATUS_AKTIF = 'aktif';
    public const STATUS_TAMAT = 'tamat';
    public const STATUS_NONAKTIF = 'nonaktif';
    public const STATUS_TA = [self::STATUS_AKTIF, self::STATUS_TAMAT, self::STATUS_NONAKTIF];

    protected $fillable = [
        'institution_id',
        'user_id',
        'judul_ta',
        'pembimbing_1_id',
        'pembimbing_2_id',
        'penguji_1_id',
        'penguji_2_id',
        'target_sesi',
        'fase',
        'status_ta',
    ];

    protected function casts(): array
    {
        return [
            'target_sesi' => 'integer',
        ];
    }

    public function faseLabel(): string
    {
        return self::FASES[$this->fase] ?? $this->fase;
    }

    public function faseIndex(): int
    {
        $keys = array_keys(self::FASES);
        $i = array_search($this->fase, $keys, true);
        return $i === false ? 0 : $i;
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
     * Health indicator bimbingan: green/yellow/red.
     * Dihitung dari interval antar tanggal_bimbingan (bukan sekadar terakhir).
     * Di-cache 6 jam per mahasiswa untuk hindari N+1.
     */
    /**
     * Data regularity (di-cache 6 jam) — satu sumber untuk status & tooltip.
     */
    public function regularityData(): array
    {
        return Cache::remember(
            "regularity:{$this->id}",
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
            ->whereIn('status', [LogbookEntry::STATUS_SUBMITTED, LogbookEntry::STATUS_APPROVED])
            ->whereNotNull('tanggal_bimbingan')
            ->orderByDesc('tanggal_bimbingan')
            ->first();

        if (!$lastEntry) {
            return ['status' => 'red', 'days_since' => null, 'avg_interval' => null];
        }

        $daysSinceLast = (int) $lastEntry->tanggal_bimbingan->diffInDays($now);

        $dates = $this->entries()
            ->whereIn('status', [LogbookEntry::STATUS_SUBMITTED, LogbookEntry::STATUS_APPROVED])
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

        $status = 'red';
        if ($daysSinceLast <= $avgInterval * 1.5) {
            $status = 'green';
        } elseif ($daysSinceLast <= $avgInterval * 2.5) {
            $status = 'yellow';
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
