<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_workspace_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_file_id')->nullable()->constrained('workspace_files')->nullOnDelete();
            $table->string('original_name');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('message_workspace_files');
    }
};