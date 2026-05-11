<?php

namespace Database\Factories;

use App\Models\Leccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProgresoLeccionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'           => User::factory(),
            'leccion_id'        => Leccion::factory(),
            'completado'        => false,
            'fecha_completado'  => null,
        ];
    }

    public function completado(): static
    {
        return $this->state([
            'completado'       => true,
            'fecha_completado' => now(),
        ]);
    }
}
