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
}
