<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai seminar/sidang dari satu dosen penilai.
 */
class SidangGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'sidang_id',
        'user_id',
        'role',
        'nilai',
        'catatan',
        'filled_at',
        'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
            'filled_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function sidang(): BelongsTo
    {
        return $this->belongsTo(Sidang::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
