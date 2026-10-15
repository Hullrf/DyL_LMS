<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\RecursoActividad;
use App\Models\CriterioRubrica;
use App\Models\NivelCriterio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Exporta un curso como plantilla ZIP (curso.json + archivos/).
 * Es el único dueño del formato: la versión y el marcador de archivos viven aquí.
 */
class CursoExportService
{
    public const VERSION_FORMATO = 1;

    /** Marcador que reemplaza URLs de archivos propios dentro de HTML. */
    public const MARCADOR = '{{archivo:%s}}';

    /** Columnas que nunca viajan: llaves foráneas, rutas de archivo (van aparte) y plazos absolutos. */
    private const EXCLUIDOS = [
        'curso_id', 'modulo_id', 'leccion_id', 'actividad_id', 'pregunta_id', 'criterio_id',
        'created_by', 'categoria_id',
        'imagen_portada', 'imagen_path', 'archivo_path',
        'fecha_apertura', 'fecha_cierre',
    ];

    private ZipArchive $zip;

    /** @var array<string,string> ruta en disco => nombre de entrada en el ZIP */
    private array $archivos = [];

    /** @var string[] */
    private array $advertencias = [];

    /** @return string ruta absoluta de un ZIP temporal; quien lo recibe debe borrarlo */
    public function exportar(Curso $curso): string
    {
        $this->archivos = [];
        $this->advertencias = [];

        $curso->load([
            'categoria',
            'modulos.lecciones.actividades.preguntas.opciones',
            'modulos.lecciones.actividades.recursos',
            'modulos.lecciones.actividades.criteriosRubrica.niveles',
        ]);

        $ruta = tempnam(sys_get_temp_dir(), 'curso');
        $this->zip = new ZipArchive();
        $this->zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $datos = $this->curso($curso);
        // Reescribe URLs de archivos propios dentro de cualquier texto (HTML de lecciones, descripciones...).
        array_walk_recursive($datos, function (&$valor) {
            if (is_string($valor)) {
                $valor = $this->reescribir($valor);
            }
        });

        $this->zip->addFromString('curso.json', json_encode([
            'version_formato' => self::VERSION_FORMATO,
            'exportado_en'    => now()->toIso8601String(),
            'advertencias'    => array_values(array_unique($this->advertencias)),
            'curso'           => $datos,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->zip->close();

        return $ruta;
    }

    private function curso(Curso $c): array
    {
        return $this->atributos($c) + [
            'categoria'      => $c->categoria ? ['nombre' => $c->categoria->nombre, 'color' => $c->categoria->color] : null,
            'imagen_portada' => $this->agregarArchivo($c->imagen_portada),
            'modulos'        => $c->modulos->map(fn (Modulo $m) => $this->atributos($m) + [
                'lecciones' => $m->lecciones->map(fn (Leccion $l) => $this->atributos($l) + [
                    'actividades' => $l->actividades->map(fn (Actividad $a) => $this->actividad($a))->all(),
                ])->all(),
            ])->all(),
        ];
    }

    private function actividad(Actividad $a): array
    {
        return $this->atributos($a) + [
            'preguntas' => $a->preguntas->map(fn (Pregunta $p) => $this->atributos($p) + [
                'imagen'   => $this->agregarArchivo($p->imagen_path),
                'opciones' => $p->opciones->map(fn (Opcion $o) => $this->atributos($o))->all(),
            ])->all(),
            'recursos'  => $a->recursos->map(fn (RecursoActividad $r) => $this->atributos($r) + [
                'archivo' => $this->agregarArchivo($r->archivo_path),
            ])->all(),
            'rubrica'   => $a->criteriosRubrica->map(fn (CriterioRubrica $c) => $this->atributos($c) + [
                'niveles' => $c->niveles->map(fn (NivelCriterio $n) => $this->atributos($n))->all(),
            ])->all(),
        ];
    }

    /** Atributos configurables del modelo: su $fillable menos FKs/archivos/plazos. */
    private function atributos(Model $modelo): array
    {
        return Arr::except($modelo->only($modelo->getFillable()), self::EXCLUIDOS);
    }

    /** Copia un archivo del disco `public` al ZIP. Devuelve el nombre de la entrada, o null si no se pudo. */
    private function agregarArchivo(?string $ruta): ?string
    {
        if (!$ruta) {
            return null;
        }
        if (isset($this->archivos[$ruta])) {
            return $this->archivos[$ruta];
        }
        $disco = Storage::disk('public');
        if (str_contains($ruta, '..') || !$disco->exists($ruta)) {
            $this->advertencias[] = "Archivo no encontrado, no se incluyó: {$ruta}";
            return null;
        }
        $entrada = 'archivos/' . $ruta;
        $this->zip->addFile($disco->path($ruta), $entrada);
        return $this->archivos[$ruta] = $entrada;
    }

    /** Cambia URLs de /storage/ de ESTA instalación por el marcador y mete el archivo al ZIP. */
    private function reescribir(string $texto): string
    {
        if (!str_contains($texto, '/storage/')) {
            return $texto;
        }
        $base = preg_quote(asset('storage') . '/', '#');

        return preg_replace_callback(
            '#(?:' . $base . '|(?<![\w:/.])/storage/)([^"\'\s)<>]+)#',
            function (array $m) {
                $ruta = urldecode($m[1]);
                return $this->agregarArchivo($ruta) ? sprintf(self::MARCADOR, $ruta) : $m[0];
            },
            $texto
        );
    }
}
