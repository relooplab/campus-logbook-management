<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstitutionWorkspaceFile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'institution_workspace_id',
        'uploaded_by',
        'original_name',
        'path',
        'mime_type',
        'size',
        'description',
        'deleted_by',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(InstitutionWorkspace::class, 'institution_workspace_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function isPdf(): bool
    {
        return str_contains((string) $this->mime_type, 'pdf');
    }
}