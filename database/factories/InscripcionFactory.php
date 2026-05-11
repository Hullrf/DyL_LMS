<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InscripcionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'curso_id'     => Curso::factory(),
            'fecha_inicio' => now(),
            'fecha_fin'    => null,
            'estado'       => 'en_progreso',
        ];
    }

    public function completado(): static
    {
        return $this->state([
            'estado'    => 'completado',
            'fecha_fin' => now(),
        ]);
    }
}
