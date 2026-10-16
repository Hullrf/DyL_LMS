<?php

namespace Tests\Feature;

use App\Models\EventoPersonal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    public function test_muestra_eventos_del_mes_y_oculta_los_de_otro_mes(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso, ['titulo' => 'De octubre', 'fecha_cierre' => '2026-10-16 23:59:00']);
        $this->actividadEn($curso, ['titulo' => 'De noviembre', 'fecha_cierre' => '2026-11-20 10:00:00']);

        $this->actingAs($u)->get(route('calendario.index', ['mes' => '2026-10']))->assertOk()
            ->assertSee('Vence: De octubre')->assertDontSee('De noviembre')->assertSee('octubre');
    }

    public function test_dia_con_cinco_eventos_los_muestra_todos(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        foreach (range(1, 5) as $i) {
            $this->actividadEn($curso, ['titulo' => "Entrega $i", 'fecha_cierre' => "2026-10-16 1{$i}:00:00"]);
        }

        $html = $this->actingAs($u)->get(route('calendario.index', ['mes' => '2026-10']))->assertOk()->getContent();

        foreach (range(1, 5) as $i) {
            $this->assertStringContainsString("Vence: Entrega $i", $html);
        }
    }

    public function test_limites_del_mes_y_cambio_de_anio(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso, ['titulo' => 'Primero', 'fecha_cierre' => '2026-12-01 00:00:00']);
        $this->actividadEn($curso, ['titulo' => 'Ultimo', 'fecha_cierre' => '2026-12-31 23:59:00']);
        $this->actividadEn($curso, ['titulo' => 'Enero', 'fecha_cierre' => '2027-01-02 09:00:00']); // en la cuadricula de diciembre

        $this->actingAs($u)->get(route('calendario.index', ['mes' => '2026-12']))->assertOk()
            ->assertSee('Vence: Primero')->assertSee('Vence: Ultimo')->assertSee('Vence: Enero');
    }

    public function test_filtro_por_curso_oculta_otros_cursos_y_eventos_personales(): void
    {
        $u = $this->estudiante();
        $c1 = $this->cursoInscrito($u);
        $c2 = $this->cursoInscrito($u);
        $this->actividadEn($c1, ['titulo' => 'Del uno', 'fecha_cierre' => '2026-10-16 10:00:00']);
        $this->actividadEn($c2, ['titulo' => 'Del dos', 'fecha_cierre' => '2026-10-16 11:00:00']);
        EventoPersonal::create(['user_id' => $u->id, 'titulo' => 'Cita medica', 'inicia_en' => '2026-10-16 08:00:00']);

        $this->actingAs($u)->get(route('calendario.index', ['mes' => '2026-10', 'curso' => $c1->id]))->assertOk()
            ->assertSee('Del uno')->assertDontSee('Del dos')->assertDontSee('Cita medica');
    }

    public function test_parametros_basura_o_arreglo_no_provocan_500(): void
    {
        $u = $this->estudiante();

        $this->actingAs($u)->get('/calendario?mes=xx')->assertOk();
        $this->actingAs($u)->get('/calendario?mes[]=2026-10&curso[]=3')->assertOk();
        $this->actingAs($u)->get('/calendario?mes=2026-13')->assertOk();
    }

    public function test_crear_y_borrar_evento_personal_y_aislamiento_entre_usuarios(): void
    {
        $u = $this->estudiante();
        $otro = $this->estudiante();

        $this->actingAs($u)->post(route('calendario.eventos.store'), [
            'titulo' => 'Estudiar', 'inicia_en' => '2026-10-20 18:00', 'descripcion' => 'Repaso',
        ])->assertRedirect();
        $evento = EventoPersonal::firstOrFail();

        $this->actingAs($u)->get(route('calendario.index', ['mes' => '2026-10']))->assertSee('Estudiar');
        $this->actingAs($otro)->get(route('calendario.index', ['mes' => '2026-10']))->assertDontSee('Estudiar');
        $this->actingAs($otro)->delete(route('calendario.eventos.destroy', $evento))->assertForbidden();

        $this->actingAs($u)->delete(route('calendario.eventos.destroy', $evento))->assertRedirect();
        $this->assertDatabaseCount('eventos_personales', 0);
    }

    public function test_validacion_del_evento_personal(): void
    {
        $u = $this->estudiante();

        $this->actingAs($u)->post(route('calendario.eventos.store'), ['titulo' => '', 'inicia_en' => 'no-fecha'])
            ->assertSessionHasErrors(['titulo', 'inicia_en']);
        $this->actingAs($u)->post(route('calendario.eventos.store'), [
            'titulo' => 'X', 'inicia_en' => '2026-10-20 18:00', 'termina_en' => '2026-10-20 17:00',
        ])->assertSessionHasErrors('termina_en');
    }

    public function test_requiere_sesion(): void
    {
        $this->get(route('calendario.index'))->assertRedirect(route('login'));
    }
}
