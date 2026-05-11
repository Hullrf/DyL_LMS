<?php

namespace Database\Factories;

use App\Models\Actividad;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreguntaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actividad_id'   => Actividad::factory(),
            'pregunta_texto' => $this->faker->sentence() . '?',
            'tipo'           => 'opcion_multiple',
            'puntaje'        => 10,
            'orden'          => $this->faker->numberBetween(0, 9),
        ];
    }
}
