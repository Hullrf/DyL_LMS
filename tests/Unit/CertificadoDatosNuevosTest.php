<?php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificadoDatosNuevosTest extends TestCase
{
    use RefreshDatabase;

    public function test_curso_tiene_tipo_certificado_diploma_por_defecto(): void
    {
        $curso = Curso::factory()->create();

        $this->assertSame('diploma', $curso->tipo_certificado);
    }

    public function test_curso_factory_diplomado_marca_el_tipo_correcto(): void
    {
        $curso = Curso::factory()->diplomado()->create();

        $this->assertSame('diplomado', $curso->tipo_certificado);
    }

    public function test_usuario_puede_guardar_numero_documento_y_ciudad_expedicion(): void
    {
        $user = User::factory()->create([
            'numero_documento'   => '1000790950',
            'ciudad_expedicion'  => 'Bogotá',
        ]);

        $user->refresh();

        $this->assertSame('1000790950', $user->numero_documento);
        $this->assertSame('Bogotá', $user->ciudad_expedicion);
    }

    public function test_usuario_sin_numero_documento_no_falla(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->numero_documento);
        $this->assertNull($user->ciudad_expedicion);
    }
}
