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
            $table->timestamp('review_opened_at')->nullable()->after('reviewed_at');
        });

        Schema::table('pdf_comments', function (Blueprint $table) {
            $table->string('resolution_status', 16)->default('open')->after('is_resolved');
        });

        DB::table('pdf_comments')
            ->where('is_resolved', true)
            ->update(['resolution_status' => 'resolved']);
    }

    public function down(): void
    {
        Schema::table('pdf_comments', function (Blueprint $table) {
            $table->dropColumn('resolution_status');
        });

        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->dropColumn('review_opened_at');
        });
    }
};
