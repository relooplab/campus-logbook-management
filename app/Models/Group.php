<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'level',
        'university_id',
        'faculty_id',
        'department_id',
        'study_program_id',
        'created_by',
    ];

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Anggota yang disetujui (approved).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('status', 'role')
            ->wherePivot('status', 'approved')
            ->withTimestamps();
    }

    /**
     * Anggota pending (menunggu approve).
     */
    public function pendingMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('status', 'role')
            ->wherePivot('status', 'pending')
            ->withTimestamps();
    }
}