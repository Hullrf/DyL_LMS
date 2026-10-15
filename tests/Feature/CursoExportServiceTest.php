<?php

namespace Tests\Feature;

use App\Services\CursoExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ArmaCursoCompleto;
use Tests\TestCase;
use ZipArchive;

class CursoExportServiceTest extends TestCase
{
    use RefreshDatabase, ArmaCursoCompleto;

    /** @return array{0: array, 1: string[]} [json decodificado, nombres de entradas] */
    private function leerZip(string $ruta): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($ruta) === true);
        $json = json_decode($zip->getFromName('curso.json'), true);
        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }
        $zip->close();
        return [$json, $nombres];
    }

    public function test_exporta_arbol_completo_y_archivos(): void
    {
        $curso = $this->cursoCompleto($this->instructor());

        $ruta = app(CursoExportService::class)->exportar($curso);
        [$json, $nombres] = $this->leerZip($ruta);
        @unlink($ruta);

        $this->assertSame(1, $json['version_formato']);
        $c = $json['curso'];
        $this->assertSame('ISO 9001', $c['titulo']);
        $this->assertSame('diplomado', $c['tipo_certificado']);
        $this->assertEquals(70, $c['nota_aprobatoria']);
        $this->assertSame(['nombre' => 'Calidad', 'color' => '#4F46E5'], $c['categoria']);
        $this->assertSame('archivos/cursos/origen/portada.jpg', $c['imagen_portada']);

        $leccion = $c['modulos'][0]['lecciones'][0];
        $this->assertStringContainsString('{{archivo:editor/2026-10/foto.png}}', $leccion['contenido_html']);
        $this->assertStringNotContainsString(asset('storage/editor'), $leccion['contenido_html']);
        // URL de otro host con /storage/ queda intacta
        $this->assertStringContainsString('https://externo.test/storage/x.png', $leccion['contenido_html']);

        $act = $leccion['actividades'][0];
        $this->assertSame(3, $act['intentos_permitidos']);
        $this->assertTrue($act['usa_rubrica']);
        $this->assertSame('archivos/cursos/origen/preguntas/p.png', $act['preguntas'][0]['imagen']);
        $this->assertCount(2, $act['preguntas'][0]['opciones']);
        $this->assertSame('archivos/cursos/origen/recursos/guia.pdf', $act['recursos'][0]['archivo']);
        $this->assertSame('Guía ISO.pdf', $act['recursos'][0]['archivo_nombre_original']);
        $this->assertSame('Claridad', $act['rubrica'][0]['nombre']);
        $this->assertEquals(5, $act['rubrica'][0]['niveles'][0]['puntos']);

        foreach (['archivos/editor/2026-10/foto.png', 'archivos/cursos/origen/portada.jpg',
                  'archivos/cursos/origen/preguntas/p.png', 'archivos/cursos/origen/recursos/guia.pdf'] as $e) {
            $this->assertContains($e, $nombres);
        }
    }

    public function test_no_exporta_ids_fks_ni_fechas_de_plazo(): void
    {
        $curso = $this->cursoCompleto($this->instructor());

        $ruta = app(CursoExportService::class)->exportar($curso);
        [$json] = $this->leerZip($ruta);
        @unlink($ruta);

        $texto = json_encode($json);
        foreach (['"id"', '"created_by"', '"curso_id"', '"modulo_id"', '"leccion_id"',
                  '"actividad_id"', '"pregunta_id"', '"criterio_id"', '"fecha_apertura"', '"fecha_cierre"'] as $clave) {
            $this->assertStringNotContainsString($clave . ':', $texto, "no debe exportar {$clave}");
        }
    }

    public function test_archivo_faltante_en_disco_no_aborta_y_queda_como_advertencia(): void
    {
        $curso = $this->cursoCompleto($this->instructor());
        Storage::disk('public')->delete('cursos/origen/recursos/guia.pdf');

        $ruta = app(CursoExportService::class)->exportar($curso);
        [$json, $nombres] = $this->leerZip($ruta);
        @unlink($ruta);

        $act = $json['curso']['modulos'][0]['lecciones'][0]['actividades'][0];
        $this->assertNull($act['recursos'][0]['archivo']);
        $this->assertNotEmpty($json['advertencias']);
        $this->assertStringContainsString('guia.pdf', $json['advertencias'][0]);
        $this->assertNotContains('archivos/cursos/origen/recursos/guia.pdf', $nombres);
    }
}
