<?php
// tests/Feature/CertificadoPlantillaCartaTest.php

namespace Tests\Feature;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoPlantillaCartaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_plantilla_renderiza_el_parrafo_completo(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create([
            'name'              => 'Darcy Carolina Cruz Guayacán',
            'numero_documento'  => '1000790950',
            'ciudad_expedicion' => 'Bogotá',
        ]);
        $curso = Curso::factory()->diplomado()->create([
            'created_by'     => $instructor->id,
            'titulo'         => 'Sistema de Gestión de la Calidad (ISO 9001:2015)',
            'duracion_horas' => 120,
        ]);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => '2026-07-29',
            'numero_certificado' => 'CERT-2026-TEST0002',
            'calificacion_final' => 100,
        ]);
        $certificado->load(['usuario', 'curso']);

        $inscripcion = Inscripcion::create([
            'user_id'      => $estudiante->id,
            'curso_id'     => $curso->id,
            'fecha_inicio' => '2026-03-28',
            'fecha_fin'    => '2026-07-28',
            'estado'       => 'completado',
        ]);

        $html = view('certificados.plantilla-carta', compact('certificado', 'inscripcion'))->render();

        $this->assertStringContainsString('DARCY CAROLINA CRUZ GUAYACÁN', mb_strtoupper($html));
        $this->assertStringContainsString('1000790950', $html);
        $this->assertStringContainsString('Bogotá', $html);
        $this->assertStringContainsString('SISTEMA DE GESTIÓN DE LA CALIDAD (ISO 9001:2015)', mb_strtoupper($html));
        $this->assertStringContainsString('120', $html);
        $this->assertStringContainsString('veintinueve', $html);
        $this->assertStringContainsString('Julio', $html);
        $this->assertStringContainsString('2026', $html);
        $this->assertStringNotContainsString('100%', $html); // no muestra calificación
    }
}
