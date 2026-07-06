<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE actividades MODIFY tipo ENUM('cuestionario','ensayo','tarea','practica','ejercicio','lectura','encuesta','reflexion') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE actividades MODIFY tipo ENUM('cuestionario','ensayo','tarea','practica') NOT NULL");
    }
};
