<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Logbook harian KP: catatan kegiatan lapangan singkat mahasiswa selama
 * periode KP. Tidak ada alur review/approval dosen.
 */
class LogbookHarianKp extends Model
{
    use HasFactory;

    protected $table = 'logbook_harian_kp';

    protected $fillable = [
        'mahasiswa_ta_id',
        'created_by',
        'tanggal',
        'kegiatan',
        'kendala',
        'foto_1',
        'foto_2',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    /**
     * Program mahasiswa (KP) pemilik catatan harian ini.
     */
    public function mahasiswaTa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaTa::class, 'mahasiswa_ta_id');
    }

    /**
     * Penulis asli catatan harian (untuk akuntabilitas KP kelompok).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
