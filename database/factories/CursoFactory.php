<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CursoFactory extends Factory
{
    protected $model = Curso::class;

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
        ];
    }

    public function create($attributes = [], $parent = null)
    {
        $model = parent::create($attributes, $parent);
        if ($model instanceof Curso) {
            $model->refresh();
        }
        return $model;
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
