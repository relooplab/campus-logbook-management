<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramNamingConfig extends Model
{
    use HasFactory;

    public const SCOPE_STUDY_PROGRAM = 'study_program';
    public const SCOPE_DEPARTMENT = 'department';
    public const SCOPES = [self::SCOPE_STUDY_PROGRAM, self::SCOPE_DEPARTMENT];

    public const JENIS_TA = 'ta';
    public const JENIS_KP = 'kp';
    public const JENISES = [self::JENIS_TA, self::JENIS_KP];

    protected $fillable = [
        'institution_id',
        'scope_type',
        'scope_id',
        'jenis',
        'program_label',
        'fase_labels',
    ];

    protected function casts(): array
    {
        return [
            'fase_labels' => 'array',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}