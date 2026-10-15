<?php

namespace Tests\Feature;

use App\Exceptions\CursoImportException;
use App\Models\Actividad;
use App\Models\Categoria;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\RecursoActividad;
use App\Services\CursoExportService;
use App\Services\CursoImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;
use ZipArchive;

class CursoImportServiceTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    private function exportar(Curso $curso): string
    {
        return app(CursoExportService::class)->exportar($curso);
    }

    /** Arma un ZIP a mano: $json es el contenido de curso.json, $archivos [entrada => contenido]. */
    private function zipDe(array $json, array $archivos = []): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'zt');
        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('curso.json', json_encode($json));
        foreach ($archivos as $entrada => $contenido) {
            $zip->addFromString($entrada, $contenido);
        }
        $zip->close();
        return $ruta;
    }

    private function cursoMinimo(array $extra = []): array
    {
        return ['version_formato' => 1, 'curso' => array_merge([
            'titulo' => 'Mini', 'descripcion' => 'd', 'duracion_horas' => 1, 'modulos' => [],
        ], $extra)];
    }

    public function test_ciclo_completo_recrea_el_curso(): void
    {
        $origen = $this->cursoCompleto($this->instructor());
        $otro   = $this->instructor();

        $zip   = $this->exportar($origen);
        $nuevo = app(CursoImportService::class)->importar($zip, $otro);
        @unlink($zip);

        $this->assertNotSame($origen->id, $nuevo->id);
        $this->assertSame('ISO 9001 (importado)', $nuevo->titulo);
        $this->assertSame('borrador', $nuevo->estado);
        $this->assertSame($otro->id, $nuevo->created_by);
        $this->assertSame('diplomado', $nuevo->tipo_certificado);
        $this->assertEquals(70, $nuevo->nota_aprobatoria);
        $this->assertSame($origen->categoria_id, $nuevo->categoria_id);
        $this->assertSame(1, Categoria::count());

        $leccion = $nuevo->modulos()->first()->lecciones()->first();
        $this->assertTrue(preg_match('#<img src="[^"]*/storage/(cursos/[^"]+)">#', $leccion->contenido_html, $m) === 1);
        $this->assertSame('PNGDATA', Storage::disk('public')->get($m[1]));
        $this->assertStringContainsString('https://externo.test/storage/x.png', $leccion->contenido_html);
        $this->assertStringNotContainsString('{{archivo:', $leccion->contenido_html);

        $act = $leccion->actividades()->first();
        $this->assertSame(3, $act->intentos_permitidos);
        $this->assertTrue($act->usa_rubrica);
        $this->assertNull($act->fecha_apertura);
        $this->assertNull($act->fecha_cierre);

        $pregunta = $act->preguntas()->first();
        $this->assertSame('PREG', Storage::disk('public')->get($pregunta->imagen_path));
        $this->assertSame(2, $pregunta->opciones()->count());
        $this->assertSame('Porque si', $pregunta->opciones()->where('es_correcta', true)->first()->explicacion);

        $recurso = $act->recursos()->first();
        $this->assertSame('PDFDATA', Storage::disk('public')->get($recurso->archivo_path));
        $this->assertSame('Guía ISO.pdf', $recurso->archivo_nombre_original);

        $nivel = $act->criteriosRubrica()->first()->niveles()->first();
        $this->assertEquals(5, $nivel->puntos);

        $this->assertSame('PORTADA', Storage::disk('public')->get($nuevo->imagen_portada));

        // El original no se tocó
        $this->assertSame('ISO 9001', $origen->fresh()->titulo);
        $this->assertTrue(Storage::disk('public')->exists('cursos/origen/portada.jpg'));
    }

    public function test_todos_los_campos_configurables_se_copian(): void
    {
        $origen = $this->cursoCompleto($this->instructor());
        $zip    = $this->exportar($origen);
        $nuevo  = app(CursoImportService::class)->importar($zip, $this->instructor());
        @unlink($zip);

        $oL = $origen->modulos()->first()->lecciones()->first();
        $nL = $nuevo->modulos()->first()->lecciones()->first();
        $oA = $oL->actividades()->first();
        $nA = $nL->actividades()->first();
        $oP = $oA->preguntas()->first();
        $nP = $nA->preguntas()->first();

        $pares = [
            [$origen->modulos()->first(), $nuevo->modulos()->first(), ['curso_id']],
            [$oL, $nL, ['modulo_id', 'contenido_html']],
            [$oA, $nA, ['leccion_id', 'fecha_apertura', 'fecha_cierre']],
            [$oP, $nP, ['actividad_id', 'imagen_path']],
            [$oP->opciones()->first(), $nP->opciones()->first(), ['pregunta_id']],
            [$oA->recursos()->first(), $nA->recursos()->first(), ['actividad_id', 'archivo_path']],
        ];
        foreach ($pares as [$orig, $copia, $excluir]) {
            foreach ($orig->getFillable() as $col) {
                if (in_array($col, $excluir, true)) {
                    continue;
                }
                $this->assertEquals($orig->$col, $copia->$col, class_basename($orig) . ".{$col}");
            }
        }
    }

    public function test_la_misma_imagen_referenciada_dos_veces_se_copia_una_sola_vez(): void
    {
        $origen = $this->cursoCompleto($this->instructor());
        $modulo = $origen->modulos()->first();
        Leccion::factory()->create([
            'modulo_id'      => $modulo->id,
            'orden'          => 1,
            'contenido_html' => '<img src="' . asset('storage/editor/2026-10/foto.png') . '">',
        ]);
        $zip   = $this->exportar($origen);
        $antes = count(Storage::disk('public')->allFiles());
        $nuevo = app(CursoImportService::class)->importar($zip, $this->instructor());
        @unlink($zip);

        // 4 archivos distintos (portada, foto, pregunta, recurso) aunque la foto se use en 2 lecciones
        $this->assertCount($antes + 4, Storage::disk('public')->allFiles());
        $urls = $nuevo->modulos()->first()->lecciones()->get()
            ->map(fn ($l) => preg_match('#/storage/(cursos/[^"]+)"#', $l->contenido_html, $m) ? $m[1] : null);
        $this->assertSame($urls[0], $urls[1]);
    }

    public function test_importar_dos_veces_genera_titulos_distintos_y_respeta_soft_delete(): void
    {
        $origen = $this->cursoCompleto($this->instructor());
        $u      = $this->instructor();
        $zip    = $this->exportar($origen);

        $a = app(CursoImportService::class)->importar($zip, $u);
        $a->delete(); // soft delete: el UNIQUE sigue ocupado
        $b = app(CursoImportService::class)->importar($zip, $u);
        $c = app(CursoImportService::class)->importar($zip, $u);
        @unlink($zip);

        $this->assertSame('ISO 9001 (importado)', $a->titulo);
        $this->assertSame('ISO 9001 (importado 2)', $b->titulo);
        $this->assertSame('ISO 9001 (importado 3)', $c->titulo);
    }

    public function test_crea_categoria_si_no_existe(): void
    {
        $origen = $this->cursoCompleto($this->instructor());
        $zip    = $this->exportar($origen);
        Curso::query()->update(['categoria_id' => null]);
        Categoria::query()->delete();

        $nuevo = app(CursoImportService::class)->importar($zip, $this->instructor());
        @unlink($zip);

        $this->assertSame('Calidad', $nuevo->categoria->nombre);
        $this->assertSame('calidad', $nuevo->categoria->slug);
    }

    public function test_rechaza_zip_con_ruta_peligrosa(): void
    {
        $zip = $this->zipDe($this->cursoMinimo(), ['../evil.txt' => 'x']);

        try {
            app(CursoImportService::class)->importar($zip, $this->instructor());
            $this->fail('Debió rechazar el ZIP');
        } catch (CursoImportException $e) {
            $this->assertStringContainsString('rutas no permitidas', $e->getMessage());
        } finally {
            @unlink($zip);
        }
        $this->assertSame(0, Curso::count());
    }

    public function test_rechaza_archivo_que_no_es_zip(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'tx');
        file_put_contents($ruta, 'esto no es un zip');

        $this->expectException(CursoImportException::class);
        try {
            app(CursoImportService::class)->importar($ruta, $this->instructor());
        } finally {
            @unlink($ruta);
        }
    }

    public function test_rechaza_zip_sin_curso_json(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'zt');
        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('otro.txt', 'x');
        $zip->close();

        $this->expectException(CursoImportException::class);
        try {
            app(CursoImportService::class)->importar($ruta, $this->instructor());
        } finally {
            @unlink($ruta);
        }
    }

    public function test_rechaza_version_de_formato_no_soportada(): void
    {
        $json = $this->cursoMinimo();
        $json['version_formato'] = 99;
        $zip = $this->zipDe($json);

        try {
            app(CursoImportService::class)->importar($zip, $this->instructor());
            $this->fail('Debió rechazar la versión');
        } catch (CursoImportException $e) {
            $this->assertStringContainsString('versión', $e->getMessage());
        } finally {
            @unlink($zip);
        }
        $this->assertSame(0, Curso::count());
    }

    public function test_marcador_sin_archivo_en_el_zip_no_deja_nada(): void
    {
        Storage::fake('public');
        $json = $this->cursoMinimo([
            'imagen_portada' => 'archivos/portada.jpg',
            'modulos' => [['titulo' => 'M', 'lecciones' => [
                ['titulo' => 'L1', 'contenido_html' => '<img src="{{archivo:editor/a.png}}">', 'actividades' => []],
                ['titulo' => 'L2', 'contenido_html' => '<img src="{{archivo:editor/falta.png}}">', 'actividades' => []],
            ]]],
        ]);
        $zip = $this->zipDe($json, ['archivos/portada.jpg' => 'P', 'archivos/editor/a.png' => 'A']);

        try {
            app(CursoImportService::class)->importar($zip, $this->instructor());
            $this->fail('Debió fallar por archivo faltante');
        } catch (CursoImportException $e) {
            $this->assertStringContainsString('falta.png', $e->getMessage());
        } finally {
            @unlink($zip);
        }
        $this->assertSame(0, Curso::count());
        $this->assertSame([], Storage::disk('public')->allFiles(), 'no deben quedar archivos huérfanos');
    }

    public function test_fallo_a_mitad_hace_rollback_de_filas_y_archivos(): void
    {
        Storage::fake('public');
        $json = $this->cursoMinimo([
            'imagen_portada' => 'archivos/portada.jpg',
            'modulos' => [['titulo' => 'M', 'lecciones' => [
                ['titulo' => 'L', 'tipo' => 'valor-invalido', 'actividades' => []],
            ]]],
        ]);
        $zip = $this->zipDe($json, ['archivos/portada.jpg' => 'P']);

        try {
            app(CursoImportService::class)->importar($zip, $this->instructor());
            $this->fail('Debió fallar por dato inválido');
        } catch (CursoImportException $e) {
            $this->assertNotSame('', $e->getMessage());
        } finally {
            @unlink($zip);
        }
        $this->assertSame(0, Curso::withTrashed()->count());
        $this->assertSame(0, Modulo::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
