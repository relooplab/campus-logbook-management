<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'npsn',
        'address',
        'city',
        'logo_path',
    ];

    /**
     * Fakultas di dalam universitas ini.
     */
    public function faculties(): HasMany
    {
        return $this->hasMany(Faculty::class);
    }

    /**
     * Pengguna yang terhubung ke universitas ini (via pivot).
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_university')
            ->withPivot('faculty_id', 'department_id', 'study_program_id', 'is_primary')
            ->withTimestamps();
    }
}