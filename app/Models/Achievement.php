<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    use HasFactory;

    /** Kode badge yang didukung. */
    public const LANGAH_PERTAMA = 'langkah_pertama';
    public const KONSISTEN = 'konsisten';
    public const ZERO_REVISI = 'zero_revisi';
    public const COMEBACK = 'comeback';
    public const SETENGAH_JALAN = 'setengah_jalan';
    public const GARIS_AKHIR = 'garis_akhir';
    public const RESPONSIF = 'responsif';
    public const TEPAT_WAKTU = 'tepat_waktu';

    protected $fillable = ['code', 'name', 'description', 'icon'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    /**
     * Daftar definisi badge (icon emoji, nama, deskripsi).
     */
    public static function definitions(): array
    {
        return [
            self::LANGAH_PERTAMA => ['🚀', 'Langkah Pertama', 'Entri logbook pertama disetujui'],
            self::KONSISTEN => ['⚡', 'Konsisten', '4 sesi beruntun tanpa jeda > 14 hari'],
            self::ZERO_REVISI => ['🎯', 'Zero Revisi', '3 entri disetujui tanpa revisi berturut-turut'],
            self::COMEBACK => ['🔥', 'Comeback', 'Submit revisi < 3 hari setelah diminta'],
            self::SETENGAH_JALAN => ['📚', 'Setengah Jalan', '50% target sesi tercapai'],
            self::GARIS_AKHIR => ['🏁', 'Garis Akhir', 'Semua target sesi disetujui'],
            self::RESPONSIF => ['💬', 'Responsif', 'Semua komentar PDF resolved'],
            self::TEPAT_WAKTU => ['⏰', 'Tepat Waktu', 'Submit < 2 hari setelah bimbingan, 5x'],
        ];
    }
}
