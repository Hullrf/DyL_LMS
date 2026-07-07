<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE recurso_actividades MODIFY COLUMN tipo ENUM('documento', 'video', 'texto', 'enlace', 'imagen') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE recurso_actividades MODIFY COLUMN tipo ENUM('documento', 'video', 'texto', 'enlace') NOT NULL");
    }
};
