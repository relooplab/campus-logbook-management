<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_name',
        'institution_name',
        'faculty',
        'study_program',
        'address',
        'city',
        'phone',
        'email',
        'website',
        'logo_path',
        'footer_note',
        'template_url',
        'max_upload_size_mb',
        'allowed_file_types',
    ];

    /**
     * Ambil profil institusi aktif (single-row), di-cache.
     */
    public static function active(): self
    {
        return Cache::remember('institution.active', now()->addDay(), function () {
            return static::first() ?? static::create([
                'app_name' => 'Thesis Logbook Management',
                'institution_name' => 'Perguruan Tinggi',
                'email' => 'no-reply@example.com',
            ]);
        });
    }

    /**
     * Sinkron nilai brand ke konfigurasi yang berjalan.
     */
    public function applyToConfig(): void
    {
        config(['app.name' => $this->app_name ?: 'Thesis Logbook Management']);

        if ($this->email) {
            config(['mail.from.address' => $this->email]);
            config(['mail.from.name' => $this->app_name ?: 'Thesis Logbook Management']);
        }
    }

    public static function flush(): void
    {
        Cache::forget('institution.active');
    }

    /**
     * Link template catatan perbaikan (bisa diisi admin).
     */
    public function templateUrl(): ?string
    {
        return $this->template_url ?: config('app.template_url');
    }

    /**
     * Batas ukuran file upload (MB) yang diatur admin.
     */
    public function maxUploadSizeMb(): int
    {
        return (int) ($this->max_upload_size_mb ?: 10);
    }

    /**
     * Daftar jenis file yang diizinkan (array). Default: pdf.
     */
    public function allowedFileTypes(): array
    {
        $raw = $this->allowed_file_types ?: 'pdf';

        return array_values(array_filter(array_map(
            fn ($t) => strtolower(trim($t)),
            explode(',', $raw)
        )));
    }

    /**
     * String mime/accept untuk input file (mis. "application/pdf,.doc,.docx").
     */
    public function fileAccept(): string
    {
        $map = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'zip' => 'application/zip',
        ];

        $accepts = [];
        foreach ($this->allowedFileTypes() as $ext) {
            if (isset($map[$ext])) {
                $accepts[] = $map[$ext];
            } else {
                $accepts[] = '.'.$ext;
            }
        }

        return implode(',', $accepts);
    }
}
