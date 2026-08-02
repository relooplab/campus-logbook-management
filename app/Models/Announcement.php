<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'institution_id',
        'title',
        'body',
        'target_filter',
    ];

    protected function casts(): array
    {
        return ['target_filter' => 'array'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_recipients')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function unreadRecipientsCount(): int
    {
        return $this->recipients()->wherePivotNull('read_at')->count();
    }
}
