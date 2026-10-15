<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Leccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class ActividadRondaSemanaTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    public function test_prefijo_se_compone_con_las_partes_presentes(): void
    {
        $a = Actividad::factory()->make(['titulo' => 'Puentes', 'ronda' => 2, 'numero_actividad' => 3, 'semana' => 9]);
        $this->assertSame('R2-A3-S9', $a->prefijo());
        $this->assertSame('R2-A3-S9 · Puentes', $a->tituloCompleto());

        $b = Actividad::factory()->make(['titulo' => 'Solo semana', 'semana' => 4]);
        $this->assertSame('S4', $b->prefijo());

        $c = Actividad::factory()->make(['titulo' => 'Sin nada']);
        $this->assertNull($c->prefijo());
        $this->assertSame('Sin nada', $c->tituloCompleto());
    }

    public function test_crear_con_titulo_prefijado_rellena_campos_y_limpia_titulo(): void
    {
        $leccion = Leccion::factory()->create();
        $a = Actividad::factory()->create([
            'leccion_id' => $leccion->id,
            'titulo'     => 'R2-A2-S10 Miremos la seguridad',
        ]);

        $this->assertSame(2, $a->ronda);
        $this->assertSame(2, $a->numero_actividad);
        $this->assertSame(10, $a->semana);
        $this->assertSame('Miremos la seguridad', $a->titulo);
    }

    #[DataProvider('titulosNoPrefijados')]
    public function test_titulos_con_prefijo_invalido_se_dejan_intactos(string $titulo): void
    {
        $a = Actividad::factory()->create(['titulo' => $titulo]);

        $this->assertSame($titulo, $a->titulo);
        $this->assertNull($a->ronda);
        $this->assertNull($a->semana);
    }

    public static function titulosNoPrefijados(): array
    {
        return [
            'ronda cero'       => ['R0-A1-S1 Algo'],
            'falta semana'     => ['R2-A3 Algo'],
            'solo el prefijo'  => ['R2-A3-S9'],
            'semana fuera'     => ['R2-A3-S99 Algo'],
        ];
    }

    public function test_instructor_guarda_y_valida_los_campos_desde_el_formulario(): void
    {
        $instructor = $this->instructor();
        $curso      = $this->cursoCompleto($instructor);
        $leccion    = $curso->lecciones()->first();

        $this->actingAs($instructor)->post(route('actividades.store', $leccion), [
            'titulo' => 'Tarea', 'tipo' => 'tarea', 'puntaje_maximo' => 5,
            'ronda' => 2, 'numero_actividad' => 1, 'semana' => 10,
        ])->assertRedirect();

        $this->assertDatabaseHas('actividades', ['titulo' => 'Tarea', 'ronda' => 2, 'semana' => 10]);

        $this->actingAs($instructor)->post(route('actividades.store', $leccion), [
            'titulo' => 'Mala', 'tipo' => 'tarea', 'puntaje_maximo' => 5, 'ronda' => 10, 'semana' => 53,
        ])->assertSessionHasErrors(['ronda', 'semana']);
    }
}
