<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'price',
        'period',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function directorySubscriptions(): HasMany
    {
        return $this->hasMany(DirectorySubscription::class);
    }

    /**
     * Ambil fitur dari plan (array). Default: free.
     */
    public function feature(string $key, mixed $default = null): mixed
    {
        return data_get($this->features, $key, $default);
    }

    /**
     * Batas penyimpanan (MB) dari plan.
     */
    public function storageLimitMb(): int
    {
        return (int) ($this->feature('storage_mb', 0));
    }
}