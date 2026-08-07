<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalizationApproval extends Model
{
    protected $fillable = ['finalization_id', 'item', 'pembimbing_id', 'status', 'alasan'];

    public function finalization(): BelongsTo
    {
        return $this->belongsTo(ThesisFinalization::class);
    }

    public function pembimbing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembimbing_id');
    }
}