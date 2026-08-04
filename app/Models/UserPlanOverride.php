<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPlanOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'allow_export',
        'allow_import',
        'storage_limit_mb',
    ];

    protected function casts(): array
    {
        return [
            'allow_export' => 'boolean',
            'allow_import' => 'boolean',
            'storage_limit_mb' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}