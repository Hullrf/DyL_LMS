<?php

namespace Tests\Feature;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\User;
use App\Services\CertificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoPlantillaDiplomaTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_pdf_de_diploma_sin_errores(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create(['name' => 'Darcy Carolina Cruz Guayacán', 'numero_documento' => '1000790950']);
        $curso      = Curso::factory()->create(['created_by' => $instructor->id, 'titulo' => 'Auditor Interno ISO 9001:2015', 'duracion_horas' => 24]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-2026-TEST0001',
            'calificacion_final' => 95,
        ]);
        $certificado->load(['usuario', 'curso.creador']);

        $ruta = app(CertificadoService::class)->generarPdf($certificado);

        $this->assertFileExists(storage_path('app/public/'.$ruta));

        @unlink(storage_path('app/public/'.$ruta));
    }
}
