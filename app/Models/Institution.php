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
        'max_upload_size_mb',
        'allowed_file_types',
        'seminar_hardcopy_note',
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ];

    /**
     * Ambil profil institusi aktif (single-row), di-cache.
     * Ini adalah fallback global — dipakai pre-auth, console command,
     * dan queue worker tanpa konteks user.
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
     * Ambil institusi berdasarkan ID, di-cache per-ID.
     * Fallback ke active() jika ID null atau tidak ditemukan.
     */
    public static function forInstitutionId(?int $id): self
    {
        if (!$id) {
            return self::active();
        }

        return Cache::remember("institution.by-id.{$id}", now()->addDay(), function () use ($id) {
            return static::find($id) ?? self::active();
        });
    }

    /**
     * Ambil institusi yang relevan untuk user tertentu.
     */
    public static function forUser(?User $user): self
    {
        return self::forInstitutionId($user?->institution_id);
    }

    /**
     * Ambil institusi yang relevan untuk user yang sedang login.
     */
    public static function current(): self
    {
        return self::forUser(auth()->user());
    }

    /**
     * Sinkron nilai brand + pengaturan email ke konfigurasi yang berjalan.
     * Dipanggil di AppServiceProvider::boot() setiap request.
     */
    public function applyToConfig(): void
    {
        config(['app.name' => $this->app_name ?: 'Thesis Logbook Management']);

        // From address: prioritas mail_from_address, fallback email institusi.
        $fromAddress = $this->mail_from_address ?: $this->email;
        $fromName = $this->mail_from_name ?: ($this->app_name ?: 'Thesis Logbook Management');

        if ($fromAddress) {
            config(['mail.from.address' => $fromAddress]);
            config(['mail.from.name' => $fromName]);
        }

        // Pengaturan SMTP dinamis (bisa diisi admin).
        if ($this->mail_mailer && ($this->mail_mailer !== 'smtp' || $this->mail_host)) {
            config(['mail.default' => $this->mail_mailer]);
        }

        if ($this->mail_host) {
            config(['mail.mailers.smtp.host' => $this->mail_host]);
        }
        if ($this->mail_port) {
            config(['mail.mailers.smtp.port' => (int) $this->mail_port]);
        }
        if ($this->mail_username !== null) {
            config(['mail.mailers.smtp.username' => $this->mail_username]);
        }
        if ($this->mail_password !== null) {
            config(['mail.mailers.smtp.password' => $this->mail_password]);
        }
        if ($this->mail_encryption !== null) {
            config(['mail.mailers.smtp.encryption' => $this->mail_encryption ?: null]);
        }
    }

    public static function flush(?int $institutionId = null): void
    {
        Cache::forget('institution.active');
        if ($institutionId) {
            Cache::forget("institution.by-id.{$institutionId}");
        }
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
