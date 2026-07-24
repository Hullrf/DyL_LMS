<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\IntentoEnProgreso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentoEnProgresoTest extends TestCase
{
    use RefreshDatabase;

    public function test_puede_crear_un_intento_en_progreso(): void
    {
        $usuario   = User::factory()->create();
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);

        $intento = IntentoEnProgreso::create([
            'user_id'      => $usuario->id,
            'actividad_id' => $actividad->id,
            'fecha_inicio' => now(),
        ]);

        $this->assertDatabaseHas('intentos_en_progreso', [
            'user_id'      => $usuario->id,
            'actividad_id' => $actividad->id,
        ]);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $intento->fresh()->fecha_inicio);
    }

    public function test_no_permite_dos_intentos_en_progreso_para_el_mismo_usuario_y_actividad(): void
    {
        $usuario   = User::factory()->create();
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);

        IntentoEnProgreso::create(['user_id' => $usuario->id, 'actividad_id' => $actividad->id, 'fecha_inicio' => now()]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        IntentoEnProgreso::create(['user_id' => $usuario->id, 'actividad_id' => $actividad->id, 'fecha_inicio' => now()]);
    }

    public function test_relaciones_usuario_y_actividad(): void
    {
        $usuario   = User::factory()->create();
        $actividad = Actividad::factory()->create(['tipo' => 'cuestionario']);
        $intento   = IntentoEnProgreso::create(['user_id' => $usuario->id, 'actividad_id' => $actividad->id, 'fecha_inicio' => now()]);

        $this->assertTrue($intento->usuario->is($usuario));
        $this->assertTrue($intento->actividad->is($actividad));
    }
}
