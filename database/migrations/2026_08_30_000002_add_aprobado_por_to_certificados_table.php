<?php
// database/migrations/2026_08_30_000002_add_aprobado_por_to_certificados_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->foreignId('aprobado_por_id')->nullable()
                ->after('calificacion_final')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprobado_por_id');
        });
    }
};
