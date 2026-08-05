<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fitur "Saya sebagai penguji" dihapus di v0.5.1 — kolom tidak lagi dipakai.
        Schema::table('users', function ($table) {
            $table->dropColumn('examiner_supervisor_names');
        });
    }

    public function down(): void
    {
        Schema::table('users', function ($table) {
            $table->json('examiner_supervisor_names')->nullable();
        });
    }
};