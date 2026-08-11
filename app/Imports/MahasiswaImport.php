<?php

namespace App\Imports;

use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import daftar mahasiswa + NIM + pembimbing dari Excel.
 * Format kolom: nama | nim | email | pembimbing1_nidn | pembimbing2_nidn
 */
class MahasiswaImport implements ToModel, WithChunkReading, WithHeadingRow
{
    /** @var array<int, string> Baris yang dilewati karena NIM bentrok dengan akun non-mahasiswa. */
    public array $errors = [];

    public function __construct(public int $defaultPembimbingId, public int $targetSesi = 7)
    {
    }

    public function model(array $row): ?MahasiswaTa
    {
        $nama = trim((string) ($row['nama'] ?? ''));
        $nim = trim((string) ($row['nim'] ?? ''));
        if ($nama === '' || $nim === '') {
            return null;
        }

        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'nim_'.$nim.'@example.com';
        }

        // Kolom `identifier` dipakai bersama untuk NIM mahasiswa & NIDN dosen
        // (unique global) — jangan timpa role akun non-mahasiswa yang sudah ada
        // bila NIM di spreadsheet kebetulan bentrok dengan nim mereka.
        $existing = User::where('nim', $nim)->first();
        if ($existing && $existing->hasAnyRole(['dosen', 'admin', 'system_admin'])) {
            $this->errors[] = "Baris NIM {$nim} ({$nama}): nim ini sudah dipakai akun non-mahasiswa ({$existing->name}), dilewati.";

            return null;
        }

        $user = $existing ?: User::create([
            'nim' => $nim,
            'name' => $nama,
            'email' => $email,
            'password' => Hash::make(Str::random(10)),
        ]);
        $user->syncRoles(['mahasiswa']);

        // Pembimbing 1 (dari NIDN di excel, fallback ke default).
        $p1 = $this->findDosen((string) ($row['pembimbing1_nidn'] ?? '')) ?? $this->defaultPembimbingId;

        MahasiswaTa::firstOrCreate(
            ['user_id' => $user->id, 'jenis' => MahasiswaTa::JENIS_TA],
            [
                'judul_ta' => 'Judul belum diisi',
                'pembimbing_1_id' => $p1,
                'pembimbing_2_id' => $this->findDosen((string) ($row['pembimbing2_nidn'] ?? '')),
                'target_sesi' => $this->targetSesi,
            ]
        );

        return null;
    }

    public function chunkSize(): int
    {
        return 200;
    }

    private function findDosen(string $nidn): ?int
    {
        if ($nidn === '') return null;
        // Dosen dicari lewat kolom NIDN (bukan nim/NIM mahasiswa).
        $dosen = User::where('nidn', $nidn)->role('dosen')->first();

        return $dosen?->id;
    }
}
