<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'institution_id',
        'name',
        'email',
        'password',
        'identifier',
        'registration_status',
        'examiner_supervisor_names',
        'profile_photo_path',
        'whatsapp',
        'telegram',
        'linkedin',
        'google_scholar',
        'orcid',
        'sinta',
        'researchgate',
        'jadwal_bimbingan_url',
        'last_active_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'examiner_supervisor_names' => 'array',
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * Institusi tempat user bergabung (NULL = mode individual).
     */
    public function institution(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * URL foto profil (atau kosong jika belum ada).
     * Foto disimpan di disk 'public' agar dapat diakses via /storage.
     */
    public function photoUrl(): string
    {
        if ($this->profile_photo_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->profile_photo_path);
        }

        return '';
    }

    /**
     * Inisial untuk avatar fallback.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name));
        $init = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $init .= mb_substr($p, 0, 1);
        }

        return strtoupper($init ?: '?');
    }

    /**
     * Normalisasi nomor WhatsApp ke tautan.
     */
    public function whatsappUrl(): ?string
    {
        if (!$this->whatsapp) return null;
        $num = preg_replace('/[^0-9]/', '', $this->whatsapp);
        if ($num === '') return null;

        return 'https://wa.me/'.$num;
    }

    /**
     * Convenience role helpers.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Achievement yang telah di-unlock user ini.
     */
    public function achievements(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Achievement::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    public function isDosen(): bool
    {
        return $this->hasRole('dosen');
    }

    public function isMahasiswa(): bool
    {
        return $this->hasRole('mahasiswa');
    }

    /**
     * Ambang batas (menit) untuk dianggap "online" sejak terakhir aktif.
     */
    public const ONLINE_THRESHOLD_MINUTES = 5;

    /**
     * Apakah user sedang online (aktif dalam 5 menit terakhir).
     */
    public function isOnline(): bool
    {
        return $this->last_active_at !== null
            && $this->last_active_at->diffInMinutes(now()) < self::ONLINE_THRESHOLD_MINUTES;
    }

    /**
     * Status ringkas: 'online' | 'offline' | 'never'.
     */
    public function lastActiveStatus(): string
    {
        if ($this->last_active_at === null) {
            return 'never';
        }

        return $this->isOnline() ? 'online' : 'offline';
    }

    /**
     * Label "terakhir aktif" untuk ditampilkan (mis. "5 menit lalu").
     */
    public function lastActiveLabel(): string
    {
        if ($this->last_active_at === null) {
            return 'Belum pernah aktif';
        }

        return $this->last_active_at->diffForHumans();
    }

    /**
     * The TA record owned by this user (mahasiswa).
     */
    public function mahasiswaTa(): HasOne
    {
        return $this->hasOne(MahasiswaTa::class, 'user_id');
    }

    /**
     * Entries where this user is recorded as the guiding dosen.
     */
    public function guidedEntries(): HasMany
    {
        return $this->hasMany(LogbookEntry::class, 'dosen_id');
    }

    /**
     * TA records where this user acts as pembimbing 1 or 2.
     */
    public function supervisedTas(): HasMany
    {
        return $this->hasMany(MahasiswaTa::class, 'pembimbing_1_id');
    }

    /**
     * Pengumuman yang diterima user ini (via pivot).
     */
    public function announcements(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Announcement::class, 'announcement_recipients')
            ->withPivot('read_at')
            ->withTimestamps();
    }
}
