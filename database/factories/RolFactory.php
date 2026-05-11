<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre'      => $this->faker->randomElement(['Administrador', 'Instructor', 'Estudiante']),
            'descripcion' => $this->faker->sentence(),
        ];
    }

    public function administrador(): static
    {
        return $this->state(['nombre' => 'Administrador']);
    }

    public function instructor(): static
    {
        return $this->state(['nombre' => 'Instructor']);
    }

    public function estudiante(): static
    {
        return $this->state(['nombre' => 'Estudiante']);
    }
}
