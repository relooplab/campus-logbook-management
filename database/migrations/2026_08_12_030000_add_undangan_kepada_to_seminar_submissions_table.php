<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ganti `undangan_sebagai` (string tunggal) menjadi `undangan_kepada`
     * (JSON array) agar mendukung banyak penerima undangan.
     */
    public function up(): void
    {
        Schema::table('seminar_submissions', function (Blueprint $table) {
            $table->json('undangan_kepada')->nullable()->after('undangan_original_name');
        });

        // Pindahkan nilai lama (string) menjadi array JSON satu elemen.
        DB::table('seminar_submissions')
            ->whereNotNull('undangan_sebagai')
            ->where('undangan_sebagai', '!=', '')
            ->orderBy('id')
            ->get(['id', 'undangan_sebagai'])
            ->each(function ($row) {
                DB::table('seminar_submissions')
                    ->where('id', $row->id)
                    ->update(['undangan_kepada' => json_encode([$row->undangan_sebagai])]);
            });

        Schema::table('seminar_submissions', function (Blueprint $table) {
            $table->dropColumn('undangan_sebagai');
        });
    }

    public function down(): void
    {
        Schema::table('seminar_submissions', function (Blueprint $table) {
            $table->string('undangan_sebagai')->nullable()->after('undangan_original_name');
        });

        // Kembalikan elemen pertama array ke kolom lama (best-effort).
        DB::table('seminar_submissions')
            ->whereNotNull('undangan_kepada')
            ->orderBy('id')
            ->get(['id', 'undangan_kepada'])
            ->each(function ($row) {
                $arr = json_decode((string) $row->undangan_kepada, true);
                $first = is_array($arr) && count($arr) ? $arr[0] : null;
                DB::table('seminar_submissions')
                    ->where('id', $row->id)
                    ->update(['undangan_sebagai' => $first]);
            });

        Schema::table('seminar_submissions', function (Blueprint $table) {
            $table->dropColumn('undangan_kepada');
        });
    }
};
