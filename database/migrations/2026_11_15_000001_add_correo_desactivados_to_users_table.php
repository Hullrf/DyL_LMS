<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Categorías de avisos por correo que el usuario apagó (null = recibe todas). */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('correo_desactivados')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('correo_desactivados');
        });
    }
};
