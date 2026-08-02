<?php

namespace App\Imports;

use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import daftar mahasiswa + NIM + pembimbing dari Excel.
 * Format kolom: nama | nim | email | pembimbing1_nidn | pembimbing2_nidn
 */
class MahasiswaImport implements ToCollection, WithHeadingRow
{
    public function __construct(public int $defaultPembimbingId, public int $targetSesi = 7)
    {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $nama = trim((string) ($row['nama'] ?? ''));
            $nim = trim((string) ($row['nim'] ?? ''));
            if ($nama === '' || $nim === '') {
                continue;
            }

            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = 'nim_'.$nim.'@example.com';
            }

            $user = User::firstOrCreate(
                ['identifier' => $nim],
                ['name' => $nama, 'email' => $email, 'password' => Hash::make('password')]
            );
            $user->syncRoles(['mahasiswa']);

            // Pembimbing 1 (dari NIDN di excel, fallback ke default).
            $p1 = $this->findDosen((string) ($row['pembimbing1_nidn'] ?? '')) ?? $this->defaultPembimbingId;

            MahasiswaTa::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'judul_ta' => 'Judul belum diisi',
                    'pembimbing_1_id' => $p1,
                    'pembimbing_2_id' => $this->findDosen((string) ($row['pembimbing2_nidn'] ?? '')),
                    'target_sesi' => $this->targetSesi,
                ]
            );
        }
    }

    private function findDosen(string $nidn): ?int
    {
        if ($nidn === '') return null;
        $dosen = User::where('identifier', $nidn)->role('dosen')->first();

        return $dosen?->id;
    }
}
