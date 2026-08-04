<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mahasiswa_ta_id',
        'uploaded_by',
        'bab',
        'original_name',
        'path',
        'mime_type',
        'size',
        'description',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function mahasiswaTa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaTa::class, 'mahasiswa_ta_id');
    }

    /**
     * Pemilik workspace pribadi (dosen) — nullable, hanya untuk workspace dosen.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Ukuran dalam format ramah-baca.
     */
    public function sizeHuman(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }

    /**
     * Ikon berdasarkan tipe file.
     */
    public function icon(): string
    {
        $mime = strtolower((string) $this->mime_type);
        if (str_contains($mime, 'pdf')) return '📕';
        if (str_contains($mime, 'word') || str_contains($mime, 'doc')) return '📘';
        if (str_contains($mime, 'excel') || str_contains($mime, 'sheet') || str_contains($mime, 'spreadsheet')) return '📗';
        return '📄';
    }

    /**
     * True bila file adalah PDF (bisa preview inline).
     */
    public function isPdf(): bool
    {
        return str_contains(strtolower((string) $this->mime_type), 'pdf');
    }

    public function scopeByBab($q, $bab)
    {
        return $q->where('bab', $bab);
    }
}
