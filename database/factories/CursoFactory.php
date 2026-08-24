<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CursoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'titulo'         => $this->faker->sentence(4),
            'descripcion'    => $this->faker->paragraph(),
            'duracion_horas' => $this->faker->numberBetween(2, 40),
            'imagen_portada' => null,
            'estado'         => 'publicado',
            'created_by'     => User::factory(),
            'orden'          => $this->faker->numberBetween(0, 10),
            'tipo_certificado' => 'diploma',
        ];
    }

    public function borrador(): static
    {
        return $this->state(['estado' => 'borrador']);
    }

    public function publicado(): static
    {
        return $this->state(['estado' => 'publicado']);
    }

    public function diplomado(): static
    {
        return $this->state(['tipo_certificado' => 'diplomado']);
    }
}
