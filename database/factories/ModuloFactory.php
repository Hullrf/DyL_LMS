<?php

namespace Database\Factories;

use App\Models\Curso;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModuloFactory extends Factory
{
    public function definition(): array
    {
        return [
            'curso_id'    => Curso::factory(),
            'titulo'      => $this->faker->sentence(3),
            'descripcion' => $this->faker->sentence(),
            'orden'       => $this->faker->numberBetween(0, 9),
        ];
    }
}
