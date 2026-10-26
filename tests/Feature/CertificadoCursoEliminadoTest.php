<?php

namespace Tests\Feature;

use App\Models\Certificado;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un certificado ya emitido no puede desaparecer ni dejar de verificarse porque el curso se archive
 * (soft delete): antes la lista, la ficha y la verificación pública respondían 500.
 */
class CertificadoCursoEliminadoTest extends TestCase
{
    use RefreshDatabase;

    private function certificadoDeCursoEliminado(): Certificado
    {
        $instructor = User::factory()->create();
        $estudiante = User::factory()->create();
        $curso = Curso::factory()->create(['created_by' => $instructor->id, 'titulo' => 'Curso archivado ISO']);

        $certificado = Certificado::create([
            'user_id'            => $estudiante->id,
            'curso_id'           => $curso->id,
            'fecha_emision'      => now()->toDateString(),
            'numero_certificado' => 'CERT-ARCHIVADO-1',
            'calificacion_final' => 90,
        ]);

        $curso->delete();

        return $certificado;
    }

    public function test_mis_certificados_lista_el_certificado_de_un_curso_archivado(): void
    {
        $certificado = $this->certificadoDeCursoEliminado();

        $this->actingAs($certificado->usuario)->get(route('certificados.mis'))
            ->assertOk()
            ->assertSee('Curso archivado ISO')
            ->assertSee('CERT-ARCHIVADO-1');
    }

    public function test_la_ficha_del_certificado_de_un_curso_archivado_responde_bien(): void
    {
        $certificado = $this->certificadoDeCursoEliminado();

        $this->actingAs($certificado->usuario)->get(route('certificados.show', $certificado))
            ->assertOk()
            ->assertSee('Curso archivado ISO');
    }

    public function test_la_verificacion_publica_sigue_validando_un_curso_archivado(): void
    {
        $this->certificadoDeCursoEliminado();

        $this->get(route('certificados.verificar', 'CERT-ARCHIVADO-1'))
            ->assertOk()
            ->assertSee('Certificado válido y auténtico')
            ->assertSee('Curso archivado ISO');
    }
}
