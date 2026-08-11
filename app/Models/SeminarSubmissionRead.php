<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pelacakan kapan seorang dosen sudah membuka sebuah submission seminar/sidang.
 * Dipakai untuk badge "Baru / Belum dibaca" pada Agenda Seminar/Sidang.
 */
class SeminarSubmissionRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'seminar_submission_id',
        'user_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(SeminarSubmission::class, 'seminar_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}