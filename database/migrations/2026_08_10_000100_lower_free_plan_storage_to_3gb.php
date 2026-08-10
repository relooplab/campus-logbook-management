<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Turunkan kuota free plan dari 5 GB (5120 MB) menjadi 3 GB (3072 MB).
     * Disesuaikan agar konsisten dengan penghapusan hard cap dosen
     * (DOSEN_STORAGE_LIMIT_MB) — kuota free plan menjadi acuan default.
     */
    private const OLD_MB = 5120;
    private const NEW_MB = 3072;

    public function up(): void
    {
        $this->updateFreePlanStorage(self::NEW_MB);
    }

    public function down(): void
    {
        $this->updateFreePlanStorage(self::OLD_MB);
    }

    private function updateFreePlanStorage(int $mb): void
    {
        $plans = DB::table('plans')->where('name', 'free')->get(['id', 'features']);
        foreach ($plans as $plan) {
            $features = $plan->features ? json_decode($plan->features, true) : [];
            if (is_array($features) && isset($features['storage_mb'])) {
                $features['storage_mb'] = $mb;
                DB::table('plans')->where('id', $plan->id)->update([
                    'features' => json_encode($features),
                ]);
            }
        }
    }
};
