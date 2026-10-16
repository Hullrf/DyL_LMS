<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class UltimoAccesoTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_se_registra_en_la_primera_visita_autenticada(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $u = $this->estudiante();
        $this->assertNull($u->fresh()->ultimo_acceso);

        $this->actingAs($u)->get('/dashboard')->assertOk();

        $this->assertTrue($u->fresh()->ultimo_acceso->equalTo(now()));
    }

    public function test_no_vuelve_a_escribir_antes_de_10_minutos_y_si_despues(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $u = $this->estudiante();
        $this->actingAs($u)->get('/dashboard');

        Carbon::setTestNow('2026-10-20 10:05:00');
        $this->actingAs($u->fresh())->get('/dashboard');
        $this->assertSame('2026-10-20 10:00:00', $u->fresh()->ultimo_acceso->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-10-20 10:11:00');
        $this->actingAs($u->fresh())->get('/dashboard');
        $this->assertSame('2026-10-20 10:11:00', $u->fresh()->ultimo_acceso->format('Y-m-d H:i:s'));
    }

    public function test_invitados_y_la_ruta_publica_no_fallan(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/calendario/feed/' . str_repeat('a', 64) . '.ics')->assertNotFound();
    }

    public function test_no_toca_updated_at_ni_genera_auditoria(): void
    {
        config(['audit.console' => true]);
        Carbon::setTestNow('2026-10-20 10:00:00');
        $u = $this->estudiante();
        $antesAuditorias = Audit::count();
        $updatedAt = $u->fresh()->updated_at;

        Carbon::setTestNow('2026-10-20 11:00:00');
        $this->actingAs($u)->get('/dashboard');

        $this->assertEquals($updatedAt, $u->fresh()->updated_at);
        $this->assertSame($antesAuditorias, Audit::count());
    }

    public function test_no_es_asignable_en_masa(): void
    {
        $u = $this->estudiante();
        $u->fill(['ultimo_acceso' => now()]);

        $this->assertNull($u->ultimo_acceso);
    }
}
