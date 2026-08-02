<?php

use App\Models\LogbookEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logbook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_ta_id')->constrained('mahasiswa_ta')->cascadeOnDelete();

            // Revisi entries do not carry dosen/tanggal/topik, hence nullable.
            $table->foreignId('dosen_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_bimbingan')->nullable();
            $table->string('topik')->nullable();

            $table->unsignedInteger('sesi_ke');
            $table->enum('jenis', LogbookEntry::JENISES);
            $table->text('progres_kendala');

            $table->string('lampiran_path')->nullable();
            $table->string('catatan_perbaikan_path')->nullable();
            $table->text('feedback_dosen')->nullable();

            $table->enum('status', LogbookEntry::STATUSES)->default(LogbookEntry::STATUS_DRAFT);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_entries');
    }
};
