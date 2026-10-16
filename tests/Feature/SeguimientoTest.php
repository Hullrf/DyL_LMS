<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\ProgresoActividad;
use App\Services\SeguimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class SeguimientoTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function curso(array $attrs = []): array
    {
        $inst  = $this->instructor();
        $curso = Curso::factory()->publicado()->create($attrs + ['created_by' => $inst->id, 'nota_aprobatoria' => 70]);

        return [$inst, $curso];
    }

    private function inscribir(Curso $curso, ?\Carbon\Carbon $ultimoAcceso, ?string $nombre = null)
    {
        $u = $this->estudiante();
        $u->forceFill(['ultimo_acceso' => $ultimoAcceso] + ($nombre ? ['name' => $nombre] : []))->save();
        Inscripcion::factory()->create(['user_id' => $u->id, 'curso_id' => $curso->id]);

        return $u;
    }

    private function fila(Curso $curso, $user)
    {
        return app(SeguimientoService::class)->para($curso)['filas']->firstWhere('usuario.id', $user->id);
    }

    public function test_estudiante_al_dia(): void
    {
        [, $curso] = $this->curso();
        $u = $this->inscribir($curso, now()->subHour());
        $a = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'fecha_cierre' => now()->subDay()]);
        $this->calificar($u, $a, 5);

        $f = $this->fila($curso, $u);

        $this->assertSame('ok', $f->nivel);
        $this->assertSame([], $f->senales);
        $this->assertSame(100, $f->promedio);
    }

    public function test_sin_acceso_justo_en_el_umbral_y_nunca_entro(): void
    {
        [, $curso] = $this->curso(); // 7 dias por defecto
        $dentro  = $this->inscribir($curso, now()->subDays(7)->addMinute());
        $fuera   = $this->inscribir($curso, now()->subDays(7)->subMinute());
        $nunca   = $this->inscribir($curso, null);

        $this->assertSame([], $this->fila($curso, $dentro)->senales);
        $this->assertSame(['sin_acceso'], $this->fila($curso, $fuera)->senales);
        $this->assertSame(['sin_acceso'], $this->fila($curso, $nunca)->senales);
        $this->assertSame('atencion', $this->fila($curso, $nunca)->nivel);
    }

    public function test_atrasadas_en_el_umbral_y_dos_senales_son_riesgo(): void
    {
        [, $curso] = $this->curso(); // 2 atrasadas por defecto
        $u = $this->inscribir($curso, now()->subDays(20));
        $this->actividadEn($curso, ['fecha_cierre' => now()->subDays(2)]);
        $a2 = $this->actividadEn($curso, ['fecha_cierre' => now()->subDay()]);

        $f = $this->fila($curso, $u);
        $this->assertSame(2, $f->atrasadas);
        $this->assertEqualsCanonicalizing(['sin_acceso', 'atrasadas'], $f->senales);
        $this->assertSame('riesgo', $f->nivel);

        ProgresoActividad::create(['user_id' => $u->id, 'actividad_id' => $a2->id, 'completado' => true, 'fecha_completado' => now()]);
        $this->assertSame(1, $this->fila($curso, $u)->atrasadas); // 1 < 2: ya no es señal
    }

    public function test_nota_baja_solo_si_hay_algo_calificado(): void
    {
        [, $curso] = $this->curso();
        $sinNada = $this->inscribir($curso, now());
        $conNota = $this->inscribir($curso, now());
        $a = $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $this->actividadEn($curso, ['puntaje_maximo' => 5]);
        $this->calificar($conNota, $a, 2); // 40 % < 70

        $this->assertSame([], $this->fila($curso, $sinNada)->senales);
        $this->assertNull($this->fila($curso, $sinNada)->promedio);
        $this->assertSame(['nota_baja'], $this->fila($curso, $conNota)->senales);
    }

    public function test_umbrales_configurables_por_curso(): void
    {
        [, $curso] = $this->curso(['seguimiento_dias_sin_acceso' => 15]);
        $u = $this->inscribir($curso, now()->subDays(10));

        $this->assertSame([], $this->fila($curso, $u)->senales);
    }

    public function test_abandonados_y_otros_cursos_no_aparecen_y_curso_vacio_no_falla(): void
    {
        [$inst, $curso] = $this->curso();
        $u = $this->estudiante();
        Inscripcion::factory()->create(['user_id' => $u->id, 'curso_id' => $curso->id, 'estado' => 'abandonado']);
        [, $otro] = $this->curso();
        $this->inscribir($otro, now());

        $datos = app(SeguimientoService::class)->para($curso);

        $this->assertCount(0, $datos['filas']);
        $this->actingAs($inst)->get(route('reportes.seguimiento', $curso))->assertOk()
            ->assertSee('Aún no hay estudiantes inscritos');
    }

    public function test_cantidad_de_consultas_no_crece_con_los_estudiantes(): void
    {
        [, $curso] = $this->curso();
        $a = $this->actividadEn($curso, ['puntaje_maximo' => 5, 'fecha_cierre' => now()->subDay()]);
        foreach (range(1, 2) as $_) { $this->calificar($this->inscribir($curso, now()), $a, 3); }
        $servicio = app(SeguimientoService::class);

        DB::enableQueryLog();
        $servicio->para($curso);
        $con2 = count(DB::getQueryLog());

        foreach (range(1, 8) as $_) { $this->calificar($this->inscribir($curso, now()), $a, 3); }
        DB::flushQueryLog();
        $servicio->para($curso);
        $con10 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($con2, $con10);
    }

    public function test_pagina_muestra_semaforo_con_texto_y_resumen(): void
    {
        [$inst, $curso] = $this->curso();
        $this->inscribir($curso, null, 'Ana Riesgo');
        $this->inscribir($curso, now(), 'Beto AlDia');

        $this->actingAs($inst)->get(route('reportes.seguimiento', $curso))->assertOk()
            ->assertSee('Ana Riesgo')->assertSee('Beto AlDia')
            ->assertSee('Atención')->assertSee('Al día')->assertSee('Nunca ha ingresado');
    }

    public function test_error_del_servicio_muestra_mensaje_con_reintento_no_pantalla_en_blanco(): void
    {
        [$inst, $curso] = $this->curso();
        $this->mock(SeguimientoService::class, fn($m) => $m->shouldReceive('para')->andThrow(new \RuntimeException('boom')));

        $this->actingAs($inst)->get(route('reportes.seguimiento', $curso))->assertOk()
            ->assertSee('No se pudo cargar el seguimiento')->assertSee('Reintentar');
    }

    public function test_actualizar_umbrales_valida_y_guarda(): void
    {
        [$inst, $curso] = $this->curso();

        $this->actingAs($inst)->put(route('reportes.seguimiento.umbrales', $curso), [
            'seguimiento_dias_sin_acceso' => 14, 'seguimiento_max_atrasadas' => 3,
        ])->assertRedirect(route('reportes.seguimiento', $curso));
        $this->assertSame(14, $curso->fresh()->seguimiento_dias_sin_acceso);

        $this->actingAs($inst)->put(route('reportes.seguimiento.umbrales', $curso), [
            'seguimiento_dias_sin_acceso' => 0, 'seguimiento_max_atrasadas' => 99,
        ])->assertSessionHasErrors(['seguimiento_dias_sin_acceso', 'seguimiento_max_atrasadas']);
    }

    public function test_csv_con_tildes_y_sin_inyeccion_de_formulas(): void
    {
        [$inst, $curso] = $this->curso();
        $this->inscribir($curso, now(), '=HYPERLINK("http://x")');
        $this->inscribir($curso, now(), 'María Pérez');

        $r = $this->actingAs($inst)->get(route('reportes.seguimiento.csv', $curso))->assertOk();
        $csv = $r->streamedContent();

        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString('María Pérez', $csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_solo_dueno_o_admin(): void
    {
        [, $curso] = $this->curso();

        foreach (['reportes.seguimiento', 'reportes.seguimiento.csv'] as $ruta) {
            $this->actingAs($this->instructor())->get(route($ruta, $curso))->assertForbidden();
            $this->actingAs($this->estudiante())->get(route($ruta, $curso))->assertForbidden();
        }
        $this->actingAs($this->admin())->get(route('reportes.seguimiento', $curso))->assertOk();
    }
}
