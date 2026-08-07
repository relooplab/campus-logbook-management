<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah status afiliasi pada pivot `user_university` untuk gate akses
 * ke Workspace Institusi:
 *
 * - `status` : pending | active | revoked.
 *   - pending  → menunggu persetujuan admin (node/institusi berlangganan).
 *   - active   → afiliasi aktif; hanya ini yang memberi akses workspace.
 *   - revoked  → afiliasi dicabut; tidak memberi akses.
 * - Afiliasi lama (nullable) dianggap `active` agar tidak mengubah perilaku.
 * - `approved_by` / `approved_at` / `rejection_reason` untuk jejak approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_university', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('is_primary');
            $table->foreignId('approved_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('rejection_reason')->nullable()->after('approved_at');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('user_university', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'approved_by', 'approved_at', 'rejection_reason']);
        });
    }
};
