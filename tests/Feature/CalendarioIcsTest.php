<?php

namespace Tests\Feature;

use App\Models\EventoPersonal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CalendarioIcsTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    public function test_exportar_descarga_ics_con_mis_eventos_y_no_los_de_otros(): void
    {
        $u = $this->estudiante();
        $otro = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso, ['titulo' => 'Mi entrega', 'fecha_cierre' => now()->addDays(3)]);
        EventoPersonal::create(['user_id' => $u->id, 'titulo' => 'Mi cita', 'inicia_en' => now()->addDay()]);
        EventoPersonal::create(['user_id' => $otro->id, 'titulo' => 'Ajeno', 'inicia_en' => now()->addDay()]);

        $r = $this->actingAs($u)->get(route('calendario.exportar'))->assertOk();

        $this->assertStringContainsString('text/calendar', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('Vence: Mi entrega', $r->getContent());
        $this->assertStringContainsString('Mi cita', $r->getContent());
        $this->assertStringNotContainsString('Ajeno', $r->getContent());
    }

    public function test_exportar_requiere_sesion(): void
    {
        $this->get(route('calendario.exportar'))->assertRedirect(route('login'));
    }

    public function test_token_crear_usar_regenerar_y_revocar(): void
    {
        $u = $this->estudiante();
        EventoPersonal::create(['user_id' => $u->id, 'titulo' => 'Evento feed', 'inicia_en' => now()->addDay()]);

        $this->actingAs($u)->post(route('calendario.token.store'))->assertRedirect();
        $t1 = $u->fresh()->calendario_token;
        $this->assertSame(64, strlen($t1));

        auth()->logout();
        $this->get(route('calendario.feed', $t1))->assertOk()->assertSee('Evento feed');

        $this->actingAs($u)->post(route('calendario.token.store'));
        $t2 = $u->fresh()->calendario_token;
        $this->assertNotSame($t1, $t2);
        auth()->logout();
        $this->get(route('calendario.feed', $t1))->assertNotFound();
        $this->get(route('calendario.feed', $t2))->assertOk();

        $this->actingAs($u)->delete(route('calendario.token.destroy'));
        auth()->logout();
        $this->get(route('calendario.feed', $t2))->assertNotFound();
    }

    public function test_token_inexistente_o_con_formato_invalido_da_404(): void
    {
        $this->get('/calendario/feed/' . str_repeat('a', 64) . '.ics')->assertNotFound();
        $this->get('/calendario/feed/corto.ics')->assertNotFound();
    }

    public function test_token_no_aparece_en_json_del_usuario(): void
    {
        $u = $this->estudiante();
        $u->forceFill(['calendario_token' => str_repeat('b', 64)])->save();

        $this->assertArrayNotHasKey('calendario_token', $u->fresh()->toArray());
    }
}
