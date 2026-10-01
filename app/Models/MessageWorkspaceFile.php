<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageWorkspaceFile extends Model
{
    protected $fillable = ['workspace_file_id', 'original_name'];

    public function file(): BelongsTo
    {
        return $this->belongsTo(WorkspaceFile::class, 'workspace_file_id');
    }
}