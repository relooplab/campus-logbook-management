<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminScope extends Model
{
    use HasFactory;

    public const SCOPE_STUDY_PROGRAM = 'study_program';
    public const SCOPE_DEPARTMENT = 'department';
    public const SCOPE_FACULTY = 'faculty';
    public const SCOPE_UNIVERSITY = 'university';
    public const SCOPES = [
        self::SCOPE_UNIVERSITY,
        self::SCOPE_FACULTY,
        self::SCOPE_DEPARTMENT,
        self::SCOPE_STUDY_PROGRAM,
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'institution_id',
        'scope_type',
        'scope_id',
        'granted_by',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Scope aktif milik user admin tertentu.
     */
    public static function activeFor(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $user->id)
            ->where('status', self::STATUS_ACTIVE)
            ->get();
    }
}