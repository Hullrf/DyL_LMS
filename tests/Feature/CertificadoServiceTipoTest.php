<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Notificacion;
use App\Models\User;
use App\Services\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoServiceTipoTest extends TestCase
{
    use RefreshDatabase;

    private function completarInscripcion(User $estudiante, Curso $curso): void
    {
        Inscripcion::create([
            'user_id' => $estudiante->id, 'curso_id' => $curso->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01', 'estado' => 'completado',
        ]);
    }

    public function test_curso_diploma_genera_certificado_normalmente(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create(['numero_documento' => null]); // sin documento, no debería importarle a un diploma
        $curso      = Curso::factory()->create(['created_by' => $instructor->id]); // tipo_certificado = diploma por defecto
        $this->completarInscripcion($estudiante, $curso);

        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso);

        $this->assertNotNull($certificado);
        $this->assertFileExists(storage_path('app/public/'.$certificado->archivo_pdf));

        @unlink(storage_path('app/public/'.$certificado->archivo_pdf));
    }

    public function test_curso_diplomado_con_documento_genera_certificado(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create(['numero_documento' => '1000790950']);
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $instructor->id]);
        $this->completarInscripcion($estudiante, $curso);

        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso);

        $this->assertNotNull($certificado);
        $this->assertFileExists(storage_path('app/public/'.$certificado->archivo_pdf));

        @unlink(storage_path('app/public/'.$certificado->archivo_pdf));
    }

    public function test_curso_diplomado_sin_documento_no_genera_y_notifica(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create(['numero_documento' => null]);
        $curso      = Curso::factory()->diplomado()->create(['created_by' => $instructor->id]);
        $this->completarInscripcion($estudiante, $curso);

        $certificado = app(CertificadoService::class)->generarSiCorresponde($estudiante, $curso);

        $this->assertNull($certificado);
        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $estudiante->id,
            'tipo'    => 'certificado',
        ]);
    }
}
