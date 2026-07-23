<?php

namespace Database\Factories;

use App\Models\Leccion;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActividadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'leccion_id'                        => Leccion::factory(),
            'tipo'                              => 'cuestionario',
            'titulo'                            => $this->faker->sentence(3),
            'descripcion'                       => $this->faker->sentence(),
            'orden'                             => $this->faker->numberBetween(0, 9),
            'puntaje_maximo'                    => 100,
            'duracion_minutos'                  => 30,
            'es_obligatoria'                    => true,
            'intentos_permitidos'               => 1,
            'criterio_calificacion_intentos'    => 'mas_alto',
            'mostrar_historial_intentos'        => true,
        ];
    }

    public function cuestionario(): static
    {
        return $this->state(['tipo' => 'cuestionario']);
    }

    public function ensayo(): static
    {
        return $this->state(['tipo' => 'ensayo']);
    }

    public function tarea(): static
    {
        return $this->state(['tipo' => 'tarea']);
    }

    public function sinNota(): static
    {
        return $this->state([
            'tipo'           => 'lectura',
            'puntaje_maximo' => null,
        ]);
    }
}
