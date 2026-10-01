<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_comment_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_comment_id')->constrained('pdf_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        DB::table('pdf_comments as c')
            ->join('logbook_entries as e', 'e.id', '=', 'c.logbook_entry_id')
            ->join('mahasiswa_ta as ta', 'ta.id', '=', 'e.mahasiswa_ta_id')
            ->whereNotNull('c.reply')
            ->select('c.id', 'c.reply', 'c.updated_at', 'ta.user_id')
            ->orderBy('c.id')
            ->chunk(200, function ($comments) {
                foreach ($comments as $comment) {
                    DB::table('pdf_comment_replies')->insert([
                        'pdf_comment_id' => $comment->id,
                        'user_id' => $comment->user_id,
                        'body' => $comment->reply,
                        'created_at' => $comment->updated_at,
                        'updated_at' => $comment->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_comment_replies');
    }
};