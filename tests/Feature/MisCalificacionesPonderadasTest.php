<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class MisCalificacionesPonderadasTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    public function test_muestra_peso_aporte_total_parcial_y_proyeccion(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 70]);
        $acts = collect(range(1, 4))->map(fn($i) => $this->actividadEn($curso, ['titulo' => "Act $i", 'puntaje_maximo' => 5, 'ponderacion' => 25]));
        $this->calificar($u, $acts[0], 5);
        $this->calificar($u, $acts[1], 3);

        $this->actingAs($u)->get(route('calificaciones.mis'))->assertOk()
            ->assertSee('Cómo se calcula tu nota')
            ->assertSee('25')                                   // peso
            ->assertSee('parcial')
            ->assertSee('Necesitas al menos 60%');
    }

    public function test_actividades_pendientes_se_muestran_con_guion_no_con_cero(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 70]);
        $a1 = $this->actividadEn($curso, ['titulo' => 'Hecha', 'puntaje_maximo' => 5]);
        $this->actividadEn($curso, ['titulo' => 'Pendiente X', 'puntaje_maximo' => 5]);
        $this->calificar($u, $a1, 5);

        $html = $this->actingAs($u)->get(route('calificaciones.mis'))->assertOk()->assertSee('Pendiente X')->getContent();

        // Solo dentro del desglose (<details>), no en otras columnas de la página
        preg_match('/<details.*?<\/details>/s', $html, $m);
        $this->assertMatchesRegularExpression('/Pendiente X.*?—/s', $m[0] ?? '');
    }

    public function test_mensaje_cuando_ya_alcanzo_y_cuando_no_es_alcanzable(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u, ['nota_aprobatoria' => 50]);
        $a1 = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 60]);
        $this->actividadEn($curso, ['puntaje_maximo' => 5, 'ponderacion' => 40]);
        $this->calificar($u, $a1, 5);
        $this->actingAs($u)->get(route('calificaciones.mis'))->assertSee('Ya alcanzaste la nota mínima');

        $u2 = $this->estudiante();
        $curso2 = $this->cursoInscrito($u2, ['nota_aprobatoria' => 100]);
        $b1 = $this->actividadEn($curso2, ['puntaje_maximo' => 5, 'ponderacion' => 60]);
        $this->actividadEn($curso2, ['puntaje_maximo' => 5, 'ponderacion' => 40]);
        $this->calificar($u2, $b1, 0);
        $this->actingAs($u2)->get(route('calificaciones.mis'))->assertSee('ya no alcanzas la nota mínima');
    }

    public function test_curso_sin_actividades_con_nota_no_rompe_la_pagina(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a = $this->actividadEn($curso, ['tipo' => 'ensayo', 'puntaje_maximo' => 5]);
        $a->update(['tipo' => 'lectura', 'puntaje_maximo' => null]);
        \App\Models\RespuestaEstudiante::factory()->create(['user_id' => $u->id, 'actividad_id' => $a->id, 'estado' => 'calificada', 'calificacion' => 0]);

        $this->actingAs($u)->get(route('calificaciones.mis'))->assertOk();
    }
}
