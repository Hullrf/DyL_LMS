<?php
// tests/Unit/CursoAprobadoPorDatosNuevosTest.php

namespace Tests\Unit;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoAprobadoPorDatosNuevosTest extends TestCase
{
    use RefreshDatabase;

    public function test_curso_tiene_nota_aprobatoria_80_por_defecto(): void
    {
        $instructor = User::factory()->create();
        $curso = Curso::factory()->create(['created_by' => $instructor->id]);

        $this->assertSame(80, $curso->fresh()->nota_aprobatoria);
    }

    public function test_curso_permite_configurar_nota_aprobatoria(): void
    {
        $instructor = User::factory()->create();
        $curso = Curso::factory()->create(['created_by' => $instructor->id, 'nota_aprobatoria' => 60]);

        $this->assertSame(60, $curso->fresh()->nota_aprobatoria);
    }

    public function test_certificado_guarda_y_expone_quien_lo_aprobo(): void
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso = Curso::factory()->create(['created_by' => $instructor->id]);

        $certificado = Certificado::create([
            'user_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'fecha_emision' => now()->toDateString(),
            'numero_certificado' => 'CERT-2026-TEST0003',
            'aprobado_por_id' => $instructor->id,
        ]);

        $this->assertSame($instructor->id, $certificado->fresh()->aprobado_por_id);
        $this->assertTrue($certificado->aprobador->is($instructor));
    }
}
