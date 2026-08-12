<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lebarkan `mail_password` ke TEXT agar cukup menampung nilai yang
     * disimpan terenkripsi (lebih panjang dari plaintext).
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->text('mail_password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('mail_password', 255)->nullable()->change();
        });
    }
};
