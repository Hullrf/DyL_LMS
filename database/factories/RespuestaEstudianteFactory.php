<?php

namespace Database\Factories;

use App\Models\Actividad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RespuestaEstudianteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'            => User::factory(),
            'actividad_id'       => Actividad::factory(),
            'respuesta'          => $this->faker->paragraph(),
            'calificacion'       => null,
            'estado'             => 'sin_calificar',
            'feedback'           => null,
            'fecha_envio'        => now(),
            'fecha_calificacion' => null,
        ];
    }

    public function calificada(): static
    {
        return $this->state([
            'calificacion'       => $this->faker->numberBetween(50, 100),
            'estado'             => 'calificada',
            'fecha_calificacion' => now(),
        ]);
    }
}
