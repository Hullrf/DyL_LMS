<?php

namespace Tests\Unit;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteServiceIntentosTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporte_por_curso_no_duplica_puntaje_con_multiples_intentos(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id, 'estado' => 'publicado']);
        $modulo     = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion    = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => now(), 'estado' => 'en_progreso',
        ]);

        $actividad = Actividad::factory()->create([
            'leccion_id' => $leccion->id, 'tipo' => 'cuestionario',
            'puntaje_maximo' => 100, 'intentos_permitidos' => 2,
            'criterio_calificacion_intentos' => 'mas_alto',
        ]);

        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 40, 'estado' => 'calificada',
        ]);
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 90, 'estado' => 'calificada',
        ]);

        $reporte = app(ReporteService::class)->reportePorCurso($curso);
        $datosEstudiante = $reporte['estudiantes']->firstWhere('usuario.id', $estudiante->id);

        // Con dedup: 90/100 = 90%. Sin dedup (bug): (40+90)/(100+100) = 65%.
        $this->assertEquals(90, $datosEstudiante['promedio']);
    }

    public function test_reporte_usa_el_ultimo_intento_cuando_la_politica_lo_indica(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso      = Curso::factory()->create(['created_by' => $instructor->id, 'estado' => 'publicado']);
        $modulo     = Modulo::factory()->create(['curso_id' => $curso->id]);
        $leccion    = Leccion::factory()->create(['modulo_id' => $modulo->id]);

        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => now(), 'estado' => 'en_progreso',
        ]);

        $actividad = Actividad::factory()->create([
            'leccion_id' => $leccion->id, 'tipo' => 'cuestionario',
            'puntaje_maximo' => 100, 'intentos_permitidos' => 2,
            'criterio_calificacion_intentos' => 'ultimo',
        ]);

        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 90, 'estado' => 'calificada', 'fecha_envio' => now()->subDay(),
        ]);
        RespuestaEstudiante::factory()->create([
            'user_id' => $estudiante->id, 'actividad_id' => $actividad->id,
            'calificacion' => 40, 'estado' => 'calificada', 'fecha_envio' => now(),
        ]);

        $reporte = app(ReporteService::class)->reportePorCurso($curso);
        $datosEstudiante = $reporte['estudiantes']->firstWhere('usuario.id', $estudiante->id);

        $this->assertEquals(40, $datosEstudiante['promedio']);
    }
}
