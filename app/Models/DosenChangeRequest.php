<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Permintaan mahasiswa untuk mengusulkan/mengganti dosen penguji.
 * Diterapkan ke mahasiswa_ta setelah semua dosen terkait (pembimbing &
 * penguji) + calon penguji baru menyetujui.
 */
class DosenChangeRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const ROLE_PENGUJI_1 = 'penguji_1';
    public const ROLE_PENGUJI_2 = 'penguji_2';

    protected $fillable = [
        'mahasiswa_ta_id',
        'requester_id',
        'proposed_role',
        'proposed_dosen_id',
        'status',
        'alasan_tolak',
    ];

    public function mahasiswaTa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaTa::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function proposedDosen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_dosen_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DosenChangeApproval::class, 'request_id');
    }

    /**
     * Daftar dosen yang wajib menyetujui: pembimbing & penguji yang sudah ada
     * di TA + calon penguji baru. Unik & tanpa null.
     *
     * @return array<int,int> user ids
     */
    public function requiredApproverIds(): array
    {
        $ta = $this->mahasiswaTa;

        return array_values(array_unique(array_filter([
            $ta?->pembimbing_1_id,
            $ta?->pembimbing_2_id,
            $ta?->penguji_1_id,
            $ta?->penguji_2_id,
            $this->proposed_dosen_id,
        ])));
    }

    /**
     * Apakah semua approver sudah menyetujui (semua approval berstatus approved).
     */
    public function isFullyApproved(): bool
    {
        $required = $this->requiredApproverIds();

        if (empty($required)) {
            return false;
        }

        $approvals = $this->approvals()->get()->keyBy('dosen_id');

        foreach ($required as $dosenId) {
            $approval = $approvals->get($dosenId);
            if (! $approval || $approval->status !== DosenChangeApproval::STATUS_APPROVED) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apakah ada satu approver yang menolak.
     */
    public function isRejected(): bool
    {
        return $this->approvals()
            ->where('status', DosenChangeApproval::STATUS_REJECTED)
            ->exists();
    }
}