<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyProgram extends Model
{
    use HasFactory;

    protected $fillable = ['department_id', 'name', 'code'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Turunkan universitas secara langsung dari prodi (leaf).
     */
    public function university(): BelongsTo
    {
        return $this->department->faculty->university();
    }
}