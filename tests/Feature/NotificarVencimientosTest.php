<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Notificacion;
use App\Models\ProgresoActividad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ArmaActividades;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class NotificarVencimientosTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto, ArmaActividades;

    private function correr(): void
    {
        $this->artisan('notificaciones:vencimientos')->assertSuccessful();
    }

    private function avisos($usuario): int
    {
        return Notificacion::where('tipo', 'vencimiento')->where('user_id', $usuario->id)->count();
    }

    public function test_avisa_a_inscritos_con_entrega_pendiente_dentro_de_24_horas(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $a = $this->actividadEn($curso, ['titulo' => 'Vence manana', 'fecha_cierre' => now()->addHours(12)]);

        $this->correr();

        $n = Notificacion::where('user_id', $u->id)->where('tipo', 'vencimiento')->firstOrFail();
        $this->assertStringContainsString('Vence manana', $n->mensaje . $n->titulo);
        $this->assertSame($a->id, $n->data['actividad_id']);
        $this->assertSame(route('actividades.show', $a), $n->data['url']);
    }

    public function test_es_idempotente(): void
    {
        $u = $this->estudiante();
        $curso = $this->cursoInscrito($u);
        $this->actividadEn($curso, ['fecha_cierre' => now()->addHours(5)]);

        $this->correr();
        $this->correr();

        $this->assertSame(1, Notificacion::where('tipo', 'vencimiento')->count());
    }

    public function test_cada_exclusion_se_respeta_y_el_control_si_es_avisado(): void
    {
        // control positivo: debe ser el unico avisado
        $control = $this->estudiante();
        $this->actividadEn($this->cursoInscrito($control), ['titulo' => 'Control', 'fecha_cierre' => now()->addHours(3)]);

        // entrego por respuesta
        $entrego = $this->estudiante();
        $this->calificar($entrego, $this->actividadEn($this->cursoInscrito($entrego), ['fecha_cierre' => now()->addHours(3)]), 5);

        // marco como hecha
        $marcada = $this->estudiante();
        $actMarcada = $this->actividadEn($this->cursoInscrito($marcada), ['fecha_cierre' => now()->addHours(3)]);
        ProgresoActividad::create(['user_id' => $marcada->id, 'actividad_id' => $actMarcada->id, 'completado' => true, 'fecha_completado' => now()]);

        // inscripcion abandonada
        $abandono = $this->estudiante();
        $cursoAb = Curso::factory()->publicado()->create();
        Inscripcion::factory()->create(['user_id' => $abandono->id, 'curso_id' => $cursoAb->id, 'estado' => 'abandonado']);
        $this->actividadEn($cursoAb, ['fecha_cierre' => now()->addHours(3)]);

        $excluidos = [$entrego, $marcada, $abandono];

        // fuera de ventana / ya vencida / sin fecha: un estudiante y curso por caso
        foreach ([now()->addHours(30), now()->subHour(), null] as $fecha) {
            $u = $this->estudiante();
            $this->actividadEn($this->cursoInscrito($u), ['fecha_cierre' => $fecha]);
            $excluidos[] = $u;
        }

        // curso no publicado
        $enBorrador = $this->estudiante();
        $borrador = Curso::factory()->borrador()->create();
        Inscripcion::factory()->create(['user_id' => $enBorrador->id, 'curso_id' => $borrador->id]);
        $this->actividadEn($borrador, ['fecha_cierre' => now()->addHours(2)]);
        $excluidos[] = $enBorrador;

        $this->correr();

        $this->assertSame(1, $this->avisos($control), 'el control positivo debe recibir su aviso');
        foreach ($excluidos as $i => $u) {
            $this->assertSame(0, $this->avisos($u), "exclusion #{$i} no debia recibir aviso");
        }
        $this->assertSame(1, Notificacion::where('tipo', 'vencimiento')->count());
    }

    public function test_actividad_sin_inscritos_no_falla(): void
    {
        $curso = Curso::factory()->publicado()->create();
        $this->actividadEn($curso, ['fecha_cierre' => now()->addHours(2)]);

        $this->correr();

        $this->assertSame(0, Notificacion::count());
    }

    public function test_esta_registrado_en_el_scheduler(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('notificaciones:vencimientos')->assertSuccessful();
    }
}
