<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tautan entri revisi ke entri asalnya (parent) + nomor ronde revisi.
     * Memungkinkan keterlacakan siklus revisi: entry -> revisi 1 -> revisi 2 -> ...
     */
    public function up(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_entry_id')->nullable()->after('id');
            $table->unsignedTinyInteger('revision_round')->nullable()->after('parent_entry_id');

            $table->foreign('parent_entry_id')
                ->references('id')
                ->on('logbook_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->dropForeign(['parent_entry_id']);
            $table->dropColumn(['parent_entry_id', 'revision_round']);
        });
    }
};