<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThesisFinalization extends Model
{
    protected $fillable = [
        'mahasiswa_ta_id', 'abstrak', 'keyword',
        'cover_path', 'cover_original_name',
        'pengesahan_path', 'pengesahan_original_name',
        'full_file_path', 'full_file_original_name',
        'abstrak_status', 'keyword_status', 'cover_status',
        'pengesahan_status', 'full_file_status', 'nilai',
    ];

    public function mahasiswaTa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaTa::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(FinalizationApproval::class, 'finalization_id');
    }

    public function isItemApproved(string $item): bool
    {
        return $this->{$item.'_status'} === 'approved';
    }

    public function allItemsApproved(): bool
    {
        $items = $this->mahasiswaTa?->isKp()
            ? ['full_file']
            : ['abstrak', 'keyword', 'cover', 'pengesahan', 'full_file'];

        foreach ($items as $item) {
            if ($this->{$item.'_status'} !== 'approved') {
                return false;
            }
        }

        return true;
    }
}