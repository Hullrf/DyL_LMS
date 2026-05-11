<?php

namespace Database\Factories;

use App\Models\Modulo;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeccionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'modulo_id'      => Modulo::factory(),
            'titulo'         => $this->faker->sentence(4),
            'contenido_html' => '<p>' . $this->faker->paragraph() . '</p>',
            'orden'          => $this->faker->numberBetween(0, 9),
            'duracion_minutos' => $this->faker->numberBetween(5, 60),
            'tipo'           => 'video',
        ];
    }
}
