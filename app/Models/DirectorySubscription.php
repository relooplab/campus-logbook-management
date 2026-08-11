<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectorySubscription extends Model
{
    use HasFactory;

    public const SCOPE_STUDY_PROGRAM = 'study_program';
    public const SCOPE_DEPARTMENT = 'department';
    public const SCOPE_FACULTY = 'faculty';
    public const SCOPE_UNIVERSITY = 'university';
    public const SCOPES = [
        self::SCOPE_STUDY_PROGRAM,
        self::SCOPE_DEPARTMENT,
        self::SCOPE_FACULTY,
        self::SCOPE_UNIVERSITY,
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'scope_type',
        'scope_id',
        'plan_id',
        'storage_limit_mb',
        'status',
        'starts_at',
        'ends_at',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Pool kuota yang dikontribusikan subscription ini (MB).
     * Prioritas: storage_limit_mb (input langsung) -> plan.storage_mb (fallback).
     */
    public function poolLimitMb(): int
    {
        if ($this->storage_limit_mb !== null) {
            return (int) $this->storage_limit_mb;
        }

        return (int) ($this->plan?->storageLimitMb() ?? 0);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Apakah langganan aktif (status active & belum lewat ends_at).
     */
    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    /**
     * Scope aktif untuk node direktori tertentu.
     */
    public static function activeFor(string $scopeType, int $scopeId): ?self
    {
        return static::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('status', self::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->first();
    }

    /**
     * Nama node direktori (mis. "S1 Teknik Informatika", "Fakultas Teknik").
     */
    public function scopeName(): string
    {
        return match ($this->scope_type) {
            self::SCOPE_STUDY_PROGRAM => \App\Models\StudyProgram::find($this->scope_id)?->name ?? "Prodi #{$this->scope_id}",
            self::SCOPE_DEPARTMENT => \App\Models\Department::find($this->scope_id)?->name ?? "Departemen #{$this->scope_id}",
            self::SCOPE_FACULTY => \App\Models\Faculty::find($this->scope_id)?->name ?? "Fakultas #{$this->scope_id}",
            self::SCOPE_UNIVERSITY => \App\Models\University::find($this->scope_id)?->name ?? "Universitas #{$this->scope_id}",
            default => "Node #{$this->scope_id}",
        };
    }

    /**
     * Label scope (mis. "Program Studi", "Fakultas").
     */
    public function scopeLabel(): string
    {
        return match ($this->scope_type) {
            self::SCOPE_STUDY_PROGRAM => 'Program Studi',
            self::SCOPE_DEPARTMENT => 'Departemen',
            self::SCOPE_FACULTY => 'Fakultas',
            self::SCOPE_UNIVERSITY => 'Universitas',
            default => 'Node',
        };
    }
}
