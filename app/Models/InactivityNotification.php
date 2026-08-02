<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InactivityNotification extends Model
{
    use HasFactory;

    protected $fillable = ['mahasiswa_ta_id', 'notified_at', 'inactive_days'];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    public function mahasiswaTa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaTa::class, 'mahasiswa_ta_id');
    }
}
