<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\IntentoEnProgreso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\ProgresoActividad;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use App\Services\LineaDeTiempoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class LineaDeTiempoTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    private function actividadEn(Curso $curso, array $attrs = []): Actividad
    {
        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        return Actividad::factory()->tarea()->create($attrs + ['leccion_id' => $leccion->id]);
    }

    private function inscrito(?Curso $curso = null): array
    {
        $user  = $this->estudiante();
        $curso = $curso ?? Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);

        return [$user, $curso];
    }

    public function test_agrupa_por_dia_y_ordena_por_hora(): void
    {
        [$user, $curso] = $this->inscrito();
        $tarde  = $this->actividadEn($curso, ['titulo' => 'Tarde',  'fecha_cierre' => now()->addDays(2)->setTime(23, 59)]);
        $pronto = $this->actividadEn($curso, ['titulo' => 'Pronto', 'fecha_cierre' => now()->addDays(2)->setTime(18, 0)]);
        $otro   = $this->actividadEn($curso, ['titulo' => 'Otro',   'fecha_cierre' => now()->addDay()]);

        $r = app(LineaDeTiempoService::class)->para($user);

        $this->assertCount(2, $r['dias']);
        $this->assertSame(['Otro'], $r['dias']->first()->pluck('actividad.titulo')->all());
        $this->assertSame(['Pronto', 'Tarde'], $r['dias']->last()->pluck('actividad.titulo')->all());
    }

    public function test_excluye_fuera_de_ventana_sin_inscripcion_y_curso_no_publicado(): void
    {
        [$user, $curso] = $this->inscrito();
        $this->actividadEn($curso, ['titulo' => 'Lejana', 'fecha_cierre' => now()->addDays(20)]);
        $this->actividadEn($curso, ['titulo' => 'SinFecha', 'fecha_cierre' => null]);

        $ajeno = Curso::factory()->publicado()->create();
        $this->actividadEn($ajeno, ['titulo' => 'Ajena', 'fecha_cierre' => now()->addDay()]);

        $borrador = Curso::factory()->borrador()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $borrador->id]);
        $this->actividadEn($borrador, ['titulo' => 'Borrador', 'fecha_cierre' => now()->addDay()]);

        $r = app(LineaDeTiempoService::class)->para($user);

        $this->assertCount(0, $r['dias']);
        $this->assertCount(0, $r['atrasadas']);
        $this->assertCount(1, app(LineaDeTiempoService::class)->para($user, 30)['dias']);
    }

    public function test_entregadas_se_ocultan_por_defecto_y_no_son_atrasadas(): void
    {
        [$user, $curso] = $this->inscrito();
        $entregada = $this->actividadEn($curso, ['titulo' => 'Entregada', 'fecha_cierre' => now()->addDay()]);
        $hechaVencida = $this->actividadEn($curso, ['titulo' => 'HechaVencida', 'fecha_cierre' => now()->subDay()]);
        $vencida = $this->actividadEn($curso, ['titulo' => 'Vencida', 'fecha_cierre' => now()->subDay()]);

        RespuestaEstudiante::factory()->create(['user_id' => $user->id, 'actividad_id' => $entregada->id]);
        ProgresoActividad::create(['user_id' => $user->id, 'actividad_id' => $hechaVencida->id, 'completado' => true, 'fecha_completado' => now()]);

        $svc = app(LineaDeTiempoService::class);
        $r   = $svc->para($user);

        $this->assertCount(0, $r['dias']);
        $this->assertSame(['Vencida'], $r['atrasadas']->pluck('actividad.titulo')->all());

        $todas = $svc->para($user, 7, null, true);
        $this->assertSame('entregada', $todas['dias']->first()->first()->estado);
        $this->assertSame('Ver', $todas['dias']->first()->first()->accion);
    }

    public function test_acciones_segun_estado_y_tipo(): void
    {
        [$user, $curso] = $this->inscrito();
        $tarea = $this->actividadEn($curso, ['fecha_cierre' => now()->addDay()]);
        $cuest = Actividad::factory()->cuestionario()->create([
            'leccion_id' => $tarea->leccion_id, 'fecha_cierre' => now()->addDays(2),
        ]);
        $enCurso = Actividad::factory()->cuestionario()->create([
            'leccion_id' => $tarea->leccion_id, 'fecha_cierre' => now()->addDays(3),
        ]);
        IntentoEnProgreso::create(['user_id' => $user->id, 'actividad_id' => $enCurso->id, 'fecha_inicio' => now()]);

        $items = app(LineaDeTiempoService::class)->para($user)['dias']->flatten(1)->keyBy('actividad.id');

        $this->assertSame('Entregar',  $items[$tarea->id]->accion);
        $this->assertSame('Responder', $items[$cuest->id]->accion);
        $this->assertSame('Continuar', $items[$enCurso->id]->accion);
    }

    public function test_filtros_por_curso_texto_y_ronda(): void
    {
        [$user, $curso] = $this->inscrito();
        $otroCurso = Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $otroCurso->id]);

        $this->actividadEn($curso,     ['titulo' => 'Infografia', 'ronda' => 2, 'fecha_cierre' => now()->addDay()]);
        $this->actividadEn($otroCurso, ['titulo' => 'Redes',      'ronda' => 3, 'fecha_cierre' => now()->addDay()]);

        $svc = app(LineaDeTiempoService::class);
        $titulos = fn($r) => $r['dias']->flatten(1)->pluck('actividad.titulo')->all();

        $this->assertSame(['Infografia'], $titulos($svc->para($user, 7, $curso->id)));
        $this->assertSame(['Redes'],      $titulos($svc->para($user, 7, null, false, 'red')));
        $this->assertSame(['Redes'],      $titulos($svc->para($user, 7, null, false, null, 3)));
    }

    public function test_inscripcion_abandonada_excluida_y_completada_incluida(): void
    {
        $user = $this->estudiante();
        $abandonado = Curso::factory()->publicado()->create();
        $completado = Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $abandonado->id, 'estado' => 'abandonado']);
        Inscripcion::factory()->create(['user_id' => $user->id, 'curso_id' => $completado->id, 'estado' => 'completado']);
        $this->actividadEn($abandonado, ['titulo' => 'Abandonada', 'fecha_cierre' => now()->addDay()]);
        $this->actividadEn($completado, ['titulo' => 'Completada', 'fecha_cierre' => now()->addDay()]);

        $svc = app(LineaDeTiempoService::class);

        $this->assertSame(['Completada'], $svc->para($user)['dias']->flatten(1)->pluck('actividad.titulo')->all());
        $mapa = $svc->proximaEntregaPorCurso($user);
        $this->assertFalse($mapa->has($abandonado->id));
        $this->assertTrue($mapa->has($completado->id));
    }

    public function test_atrasadas_excluye_entregadas_antes_del_limite(): void
    {
        [$user, $curso] = $this->inscrito();
        foreach ([1, 2] as $i) {
            $this->actividadEn($curso, ['titulo' => "Pend$i", 'fecha_cierre' => now()->subDays(10 + $i)]);
        }
        foreach ([1, 2, 3] as $i) {
            $a = $this->actividadEn($curso, ['titulo' => "Hecha$i", 'fecha_cierre' => now()->subDays($i)]);
            RespuestaEstudiante::factory()->create(['user_id' => $user->id, 'actividad_id' => $a->id]);
        }

        $r = app(LineaDeTiempoService::class)->para($user);

        $this->assertSame(['Pend1', 'Pend2'], $r['atrasadas']->pluck('actividad.titulo')->all());
    }

    public function test_proxima_entrega_por_curso(): void
    {
        [$user, $curso] = $this->inscrito();
        $this->actividadEn($curso, ['fecha_cierre' => now()->addDays(5)]);
        $cercana = $this->actividadEn($curso, ['fecha_cierre' => now()->addDays(2)]);
        $this->actividadEn($curso, ['fecha_cierre' => now()->subDay()]);

        $mapa = app(LineaDeTiempoService::class)->proximaEntregaPorCurso($user);

        $this->assertTrue($mapa[$curso->id]->equalTo($cercana->fecha_cierre));
    }

    public function test_pagina_muestra_entregas_atrasadas_y_accion(): void
    {
        [$user, $curso] = $this->inscrito();
        $this->actividadEn($curso, ['titulo' => 'Puentes invisibles', 'fecha_cierre' => now()->addDay()->setTime(18, 0)]);
        $this->actividadEn($curso, ['titulo' => 'Tarea vieja', 'fecha_cierre' => now()->subDays(2)]);

        $this->actingAs($user)->get(route('linea-de-tiempo.index'))
            ->assertOk()
            ->assertSee('Puentes invisibles')
            ->assertSee('18:00')
            ->assertSee('Entregar')
            ->assertSee('Atrasadas')
            ->assertSee('Tarea vieja');
    }

    public function test_pagina_vacia_muestra_mensaje(): void
    {
        [$user] = $this->inscrito();

        $this->actingAs($user)->get(route('linea-de-tiempo.index'))
            ->assertOk()->assertSee('No tienes entregas próximas');
    }

    public function test_dias_invalidos_caen_al_valor_por_defecto_y_requiere_login(): void
    {
        [$user] = $this->inscrito();

        $this->actingAs($user)->get(route('linea-de-tiempo.index', ['dias' => 'abc']))->assertOk();
        auth()->logout();
        $this->get(route('linea-de-tiempo.index'))->assertRedirect(route('login'));
    }

    public function test_dashboard_del_estudiante_incluye_el_bloque(): void
    {
        [$user, $curso] = $this->inscrito();
        $this->actividadEn($curso, ['titulo' => 'Vence pronto', 'fecha_cierre' => now()->addDay()]);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Próximas entregas')->assertSee('Vence pronto');
    }
}
