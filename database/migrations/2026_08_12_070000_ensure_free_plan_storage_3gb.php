<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pastikan plan FREE selalu ber-storage 3 GB (3072 MB).
 *
 * Sebelumnya, plan `free` bisa terlanjur ada tanpa `storage_mb` (mis. dibuat
 * sebelum seeder/migrasi storage_mb, atau `firstOrCreate` tidak mengupdate).
 * Akibatnya kuota dosen default = 0. Migration ini memperbaiki data yang sudah
 * ada: update storage_mb = 3072 jika plan free ada, atau buat jika belum ada.
 * Idempotent.
 */
return new class extends Migration
{
    private const FREE_STORAGE_MB = 3072;

    public function up(): void
    {
        $free = DB::table('plans')->where('name', 'free')->first();

        if ($free) {
            $features = json_decode($free->features ?? '', true);
            if (! is_array($features)) {
                $features = [];
            }
            $features['storage_mb'] = self::FREE_STORAGE_MB;

            DB::table('plans')->where('id', $free->id)->update([
                'features' => json_encode($features),
                'is_active' => true,
            ]);

            return;
        }

        DB::table('plans')->insert([
            'name' => 'free',
            'label' => 'Gratis',
            'price' => 0,
            'period' => 'monthly',
            'features' => json_encode([
                'export' => false,
                'import' => false,
                'storage_mb' => self::FREE_STORAGE_MB,
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Idempotent; tidak ada rollback bermakna.
    }
};
