<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionWorkspace extends Model
{
    use HasFactory;

    public const SCOPE_UNIVERSITY = 'university';
    public const SCOPE_FACULTY = 'faculty';
    public const SCOPE_DEPARTMENT = 'department';
    public const SCOPE_STUDY_PROGRAM = 'study_program';
    public const SCOPES = [
        self::SCOPE_UNIVERSITY,
        self::SCOPE_FACULTY,
        self::SCOPE_DEPARTMENT,
        self::SCOPE_STUDY_PROGRAM,
    ];

    public const ACCESS_HIERARCHICAL = 'hierarchical';
    public const ACCESS_CUSTOM = 'custom';

    protected $fillable = [
        'institution_id',
        'scope_type',
        'scope_id',
        'name',
        'access_mode',
        'created_by',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(InstitutionWorkspaceFile::class);
    }

    public function allowedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'institution_workspace_allowed_users', 'institution_workspace_id', 'user_id')
            ->withTimestamps();
    }

    public function scopeName(): string
    {
        return match ($this->scope_type) {
            self::SCOPE_STUDY_PROGRAM => StudyProgram::find($this->scope_id)?->name ?? "Prodi #{$this->scope_id}",
            self::SCOPE_DEPARTMENT => Department::find($this->scope_id)?->name ?? "Departemen #{$this->scope_id}",
            self::SCOPE_FACULTY => Faculty::find($this->scope_id)?->name ?? "Fakultas #{$this->scope_id}",
            self::SCOPE_UNIVERSITY => University::find($this->scope_id)?->name ?? "Universitas #{$this->scope_id}",
            default => "Node #{$this->scope_id}",
        };
    }

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

    /**
     * Apakah user punya akses lihat/download workspace ini.
     */
    public function isAccessibleBy(User $user): bool
    {
        // Admin: akses jika di simpul yang sama (scope_type + scope_id cocok)
        // ATAU di-grant custom.
        if ($user->isAdmin()) {
            return $this->isAdminAtSameNode($user) || $this->allowedUsers->contains('id', $user->id);
        }

        // Dosen: akses jika di-grant custom,
        // ATAU mode default & prodi sama dengan scope workspace.
        if ($user->isDosen()) {
            if ($this->allowedUsers->contains('id', $user->id)) {
                return true;
            }

            return $this->access_mode === self::ACCESS_HIERARCHICAL
                && $this->scope_type === self::SCOPE_STUDY_PROGRAM
                && $this->scope_id === $this->userPrimaryStudyProgramId($user);
        }

        return false;
    }

    /**
     * Apakah admin punya kewenangan kelola (upload/hapus/atur akses) workspace ini.
     * Admin di simpul yang sama punya kewenangan penuh.
     */
    public function canManage(User $user): bool
    {
        if (!$user->isAdmin()) {
            return false;
        }

        return $this->isAdminAtSameNode($user) || $this->allowedUsers->contains('id', $user->id);
    }

    /**
     * Apakah admin punya admin_scope di simpul yang sama dengan workspace ini.
     */
    private function isAdminAtSameNode(User $admin): bool
    {
        return \App\Models\AdminScope::where('user_id', $admin->id)
            ->where('status', \App\Models\AdminScope::STATUS_ACTIVE)
            ->where('scope_type', $this->scope_type)
            ->where('scope_id', $this->scope_id)
            ->exists();
    }

    /**
     * Prodi utama dari afiliasi yg BERSTATUS ACTIVE (disetujui).
     * Afiliasi pending/revoked TIDAK memberi akses ke Workspace Institusi.
     */
    private function userPrimaryStudyProgramId(User $user): ?int
    {
        $pivot = $user->universities()
            ->wherePivot('is_primary', true)
            ->wherePivot('status', 'active')
            ->first();

        if ($pivot) {
            return $pivot->pivot->study_program_id;
        }

        // Fallback: universitas pertama yang statusnya active.
        $first = $user->universities()
            ->wherePivot('status', 'active')
            ->first();

        return $first?->pivot->study_program_id;
    }
}
