<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intentos_extra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
            $table->unsignedTinyInteger('cantidad')->default(0);
            $table->foreignId('otorgado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->unique(['user_id', 'actividad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intentos_extra');
    }
};
