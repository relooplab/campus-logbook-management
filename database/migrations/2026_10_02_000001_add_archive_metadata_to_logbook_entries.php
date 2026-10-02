<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->text('archive_reason')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Kode lama tidak mengenal `archived`: kembalikan ke antrean sebelum
        // metadata arsip dihapus agar entri tidak menghilang tanpa jejak status.
        DB::table('logbook_entries')->where('status', 'archived')->update([
            'status' => 'submitted',
            'reviewed_at' => null,
        ]);

        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn(['archive_reason', 'archived_at']);
        });
    }
};
