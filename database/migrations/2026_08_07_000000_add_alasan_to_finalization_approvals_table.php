<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finalization_approvals', function (Blueprint $table) {
            // Alasan wajib saat dosen menolak item finalisasi (jejak siapa & kenapa).
            $table->text('alasan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('finalization_approvals', function (Blueprint $table) {
            $table->dropColumn('alasan');
        });
    }
};
