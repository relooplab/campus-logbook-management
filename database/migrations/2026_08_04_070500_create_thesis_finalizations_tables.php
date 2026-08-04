<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thesis_finalizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_ta_id')->constrained('mahasiswa_ta')->cascadeOnDelete();
            $table->text('abstrak')->nullable();
            $table->string('keyword')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('cover_original_name')->nullable();
            $table->string('pengesahan_path')->nullable();
            $table->string('pengesahan_original_name')->nullable();
            $table->string('full_file_path')->nullable();
            $table->string('full_file_original_name')->nullable();
            $table->string('abstrak_status')->default('draft');
            $table->string('keyword_status')->default('draft');
            $table->string('cover_status')->default('draft');
            $table->string('pengesahan_status')->default('draft');
            $table->string('full_file_status')->default('draft');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->timestamps();
            $table->unique('mahasiswa_ta_id');
        });

        Schema::create('finalization_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finalization_id')->constrained('thesis_finalizations')->cascadeOnDelete();
            $table->string('item');
            $table->foreignId('pembimbing_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['finalization_id', 'item', 'pembimbing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finalization_approvals');
        Schema::dropIfExists('thesis_finalizations');
    }
};