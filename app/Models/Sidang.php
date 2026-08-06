<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sidang extends Model
{
    use HasFactory;

    public const JENIS_PROPOSAL = 'seminar_proposal';
    public const JENIS_SIDANG = 'sidang_akhir';
    public const JENISES = [self::JENIS_PROPOSAL, self::JENIS_SIDANG];

    public const HASIL_LULUS = 'lulus';
    public const HASIL_LULUS_REVISI = 'lulus_revisi';
    public const HASIL_MENGULANG = 'mengulang';
    public const HASILS = [self::HASIL_LULUS, self::HASIL_LULUS_REVISI, self::HASIL_MENGULANG];

    protected $fillable = [
        'institution_id',
        'mahasiswa_ta_id',
        'mahasiswa_name',
        'penguji_id',
        'jenis',
        'tanggal',
        'hasil',
        'supervisor_names',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'supervisor_names' => 'array',
        ];
    }

    public function mahasiswaTa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaTa::class, 'mahasiswa_ta_id');
    }

    public function penguji(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penguji_id');
    }

    public function jenisLabel(): string
    {
        // Label fase kustom dari prodi/departemen (jika ada).
        if ($this->mahasiswaTa) {
            $labels = app(\App\Services\ProgramNamingService::class)->faseLabels($this->mahasiswaTa);

            $faseKey = $this->mahasiswaTa->isKp()
                ? 'seminar_kp'
                : ($this->jenis === self::JENIS_SIDANG ? 'sidang' : 'proposal');

            if (isset($labels[$faseKey])) {
                return $labels[$faseKey];
            }
        }

        // Seminar KP memakai tabel sidang yang sama.
        if ($this->mahasiswaTa?->isKp()) {
            return 'Seminar KP';
        }

        return $this->jenis === self::JENIS_SIDANG ? 'Sidang Akhir' : 'Seminar Proposal';
    }

    public function hasilLabel(): string
    {
        return match ($this->hasil) {
            self::HASIL_LULUS => 'Lulus',
            self::HASIL_LULUS_REVISI => 'Lulus + Revisi',
            self::HASIL_MENGULANG => 'Mengulang',
            default => '—',
        };
    }
}
