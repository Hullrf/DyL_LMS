<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE recurso_actividades MODIFY COLUMN tipo ENUM('documento', 'video', 'texto', 'enlace', 'imagen') NOT NULL");
        }
        // SQLite doesn't support ENUM or column modifications, skip
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE recurso_actividades MODIFY COLUMN tipo ENUM('documento', 'video', 'texto', 'enlace') NOT NULL");
        }
        // SQLite doesn't support ENUM or column modifications, skip
    }
};
