<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Services\CursoExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;

class CursoPlantillaControllerTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    private function zipSubido(Curso $curso): UploadedFile
    {
        $ruta = app(CursoExportService::class)->exportar($curso);
        return new UploadedFile($ruta, 'plantilla.zip', 'application/zip', null, true);
    }

    public function test_dueno_descarga_el_zip(): void
    {
        $dueno = $this->instructor();
        $curso = $this->cursoCompleto($dueno);

        $r = $this->actingAs($dueno)->get(route('cursos.exportar', $curso));

        $r->assertOk();
        $this->assertStringContainsString('curso-iso-9001.zip', $r->headers->get('content-disposition'));
    }

    public function test_admin_puede_exportar_cualquier_curso(): void
    {
        $curso = $this->cursoCompleto($this->instructor());
        $this->actingAs($this->admin())->get(route('cursos.exportar', $curso))->assertOk();
    }

    public function test_instructor_ajeno_y_estudiante_no_exportan(): void
    {
        $curso = $this->cursoCompleto($this->instructor());

        $this->actingAs($this->instructor())->get(route('cursos.exportar', $curso))->assertForbidden();
        $this->actingAs($this->estudiante())->get(route('cursos.exportar', $curso))->assertForbidden();
    }

    public function test_instructor_importa_y_va_a_editar_el_curso_nuevo(): void
    {
        $instructor = $this->instructor();
        $curso      = $this->cursoCompleto($instructor);

        $r = $this->actingAs($instructor)->post(route('cursos.importar'), ['archivo' => $this->zipSubido($curso)]);

        $nuevo = Curso::where('titulo', 'ISO 9001 (importado)')->firstOrFail();
        $r->assertRedirect(route('cursos.edit', $nuevo));
        $r->assertSessionHas('success');
        $this->assertSame($instructor->id, $nuevo->created_by);
    }

    public function test_estudiante_no_importa(): void
    {
        $curso = $this->cursoCompleto($this->instructor());

        $this->actingAs($this->estudiante())
            ->post(route('cursos.importar'), ['archivo' => $this->zipSubido($curso)])
            ->assertForbidden();
        $this->assertSame(1, Curso::count());
    }

    public function test_sin_archivo_vuelve_con_error(): void
    {
        $r = $this->actingAs($this->instructor())->from(route('cursos.index'))->post(route('cursos.importar'), []);

        $r->assertRedirect(route('cursos.index'));
        $r->assertSessionHas('error');
    }

    public function test_archivo_que_no_es_zip_vuelve_con_error_sin_crear_nada(): void
    {
        $falso = UploadedFile::fake()->createWithContent('basura.zip', 'esto no es un zip');

        $r = $this->actingAs($this->instructor())->from(route('cursos.index'))
            ->post(route('cursos.importar'), ['archivo' => $falso]);

        $r->assertRedirect(route('cursos.index'));
        $r->assertSessionHas('error');
        $this->assertSame(0, Curso::count());
    }

    public function test_los_botones_se_muestran_al_instructor(): void
    {
        $dueno = $this->instructor();
        $curso = $this->cursoCompleto($dueno);

        $this->actingAs($dueno)->get(route('cursos.edit', $curso))
            ->assertSee(route('cursos.exportar', $curso), false);
        $this->actingAs($dueno)->get(route('cursos.index'))
            ->assertSee(route('cursos.importar'), false);
    }
}
