<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use App\Services\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoServiceIntentosTest extends TestCase
{
    use RefreshDatabase;

    public function test_calificacion_final_no_duplica_puntaje_con_multiples_intentos(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id, 'estado' => 'publicado']);
        $modulo     = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion    = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => now(), 'fecha_fin' => now()->toDateString(), 'estado' => 'completado',
        ]);

        $actividad = Actividad::factory()->create([
            'leccion_id' => $leccion->id, 'tipo' => 'cuestionario',
            'puntaje_maximo' => 100, 'intentos_permitidos' => 2,
            'criterio_calificacion_intentos' => 'mas_alto',
        ]);

        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 50, 'estado' => 'calificada',
        ]);
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 100, 'estado' => 'calificada',
        ]);

        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso, $instructor);

        $this->assertNotNull($certificado);
        $this->assertEquals(100, $certificado->calificacion_final);
    }
}
