<?php

use App\Models\PdfComment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logbook_entry_id')->constrained('logbook_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('file_type', PdfComment::FILE_TYPES);
            $table->unsignedInteger('page_number');
            $table->float('pos_x')->nullable();
            $table->float('pos_y')->nullable();
            $table->float('x2')->nullable();
            $table->float('y2')->nullable();
            $table->text('comment');
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_comments');
    }
};
