<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogbookEntry extends Model
{
    use HasFactory;

    /** Jenis submission. */
    public const JENIS_LOGBOOK = 'logbook';
    public const JENIS_REVISI = 'revisi';

    /** Status workflow. */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVISI = 'revisi';

    public const JENISES = [self::JENIS_LOGBOOK, self::JENIS_REVISI];
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_REVISI,
    ];

    protected $fillable = [
        'mahasiswa_ta_id',
        'dosen_id',
        'tanggal_bimbingan',
        'tanggal_pengiriman',
        'topik',
        'sesi_ke',
        'jenis',
        'progres_kendala',
        'lampiran_path',
        'lampiran_original_name',
        'catatan_perbaikan_path',
        'catatan_original_name',
        'feedback_dosen',
        'status',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_bimbingan' => 'date',
            'tanggal_pengiriman' => 'date',
            'sesi_ke' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function mahasiswaTa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaTa::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PdfComment::class, 'logbook_entry_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class, 'logbook_entry_id');
    }

    public function scopeJenis($query, $jenis): Builder
    {
        return $query->where('jenis', $jenis);
    }

    public function scopeStatus($query, $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * True when the entry still allows editing by the owner (draft/revisi).
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVISI], true);
    }

    /**
     * Tanggal yang ditampilkan: Tanggal Bimbingan untuk logbook,
     * Tanggal Pengiriman untuk revisi (agar tidak kosong).
     */
    public function getTanggalTampilAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->jenis === self::JENIS_REVISI
            ? ($this->tanggal_pengiriman ?? $this->submitted_at?->toDate())
            : $this->tanggal_bimbingan;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Resolve the dosen who is expected to review this entry, following the
     * assignment priority from the spec:
     *   1. mahasiswa_ta.pembimbing_1_id
     *   2. mahasiswa_ta.pembimbing_2_id
     *   3. entry.dosen_id (fallback)
     */
    public function reviewDosen(): ?User
    {
        $ta = $this->mahasiswaTa;

        if ($ta) {
            if ($ta->pembimbing_1_id) {
                return User::find($ta->pembimbing_1_id);
            }
            if ($ta->pembimbing_2_id) {
                return User::find($ta->pembimbing_2_id);
            }
        }

        return $this->dosen_id ? $this->dosen : null;
    }

    /**
     * Notify pemilik TA + pembimbing (DB + email) dengan pesan tertentu.
     */
    public function notifyParties(string $message, ?string $url = null, string $subject = 'Pemberitahuan Thesis Logbook Management'): void
    {
        $recipients = [];
        if ($ownerId = $this->mahasiswaTa?->user_id) {
            $recipients[] = $ownerId;
        }
        if ($dosen = $this->reviewDosen()) {
            $recipients[] = $dosen->id;
        }

        $recipients = array_unique(array_filter($recipients));

        foreach ($recipients as $id) {
            if ($user = User::find($id)) {
                $user->notify(new \App\Notifications\ActivityNotification($message, $url, $subject));
            }
        }
    }

    /**
     * Notify pembimbing bahwa ada entri baru menunggu review.
     */
    public function notifyDosen(string $message, ?string $url = null, string $subject = 'Entri Baru Menunggu Review'): void
    {
        if ($dosen = $this->reviewDosen()) {
            $dosen->notify(new \App\Notifications\ActivityNotification($message, $url, $subject));
        }
    }
}
