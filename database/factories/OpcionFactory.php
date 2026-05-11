<?php

namespace Database\Factories;

use App\Models\Pregunta;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpcionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pregunta_id' => Pregunta::factory(),
            'texto'       => $this->faker->sentence(4),
            'es_correcta' => false,
            'explicacion' => null,
            'orden'       => $this->faker->numberBetween(0, 3),
        ];
    }

    public function correcta(): static
    {
        return $this->state(['es_correcta' => true]);
    }
}
