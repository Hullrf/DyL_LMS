<?php

namespace App\Services;

use App\Exceptions\CursoImportException;
use App\Models\Actividad;
use App\Models\Categoria;
use App\Models\CriterioRubrica;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\NivelCriterio;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\RecursoActividad;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Importa una plantilla ZIP creada por CursoExportService.
 * Siempre crea un curso nuevo (borrador) dentro de una sola transacción.
 */
class CursoImportService
{
    private const MAX_ENTRADAS = 5000;
    private const MAX_BYTES    = 524288000; // 500 MB descomprimidos
    private const EXTENSIONES  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'ppt', 'pptx',
        'xls', 'xlsx', 'zip', 'html', 'mp4', 'mov', 'avi', 'webm'];

    private ZipArchive $zip;
    private string $dir = '';

    /** @var array<string,string> entrada del ZIP => ruta nueva en el disco */
    private array $copiados = [];

    public function importar(string $rutaZip, User $usuario): Curso
    {
        $this->copiados = [];
        $this->zip = new ZipArchive();
        if ($this->zip->open($rutaZip, ZipArchive::RDONLY) !== true) {
            throw new CursoImportException('El archivo no es un ZIP válido.');
        }

        try {
            $this->validarEntradas();
            $datos = $this->leerJson();

            return DB::transaction(fn () => $this->crear($datos['curso'], $usuario));
        } catch (CursoImportException $e) {
            $this->limpiar();
            throw $e;
        } catch (Throwable $e) {
            $this->limpiar();
            report($e);
            throw new CursoImportException('No se pudo importar el curso: el archivo está incompleto o tiene datos inválidos.');
        } finally {
            $this->zip->close();
        }
    }

    private function validarEntradas(): void
    {
        if ($this->zip->numFiles > self::MAX_ENTRADAS) {
            throw new CursoImportException('El ZIP contiene demasiados archivos.');
        }
        $total = 0;
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat   = $this->zip->statIndex($i);
            $nombre = $stat['name'];
            if (str_contains($nombre, '..') || str_contains($nombre, '\\')
                || str_starts_with($nombre, '/') || preg_match('/^[A-Za-z]:/', $nombre)) {
                throw new CursoImportException('El ZIP contiene rutas no permitidas.');
            }
            $total += $stat['size'];
        }
        if ($total > self::MAX_BYTES) {
            throw new CursoImportException('El contenido del ZIP es demasiado grande.');
        }
    }

    private function leerJson(): array
    {
        $crudo = $this->zip->getFromName('curso.json');
        if ($crudo === false) {
            throw new CursoImportException('El ZIP no contiene curso.json.');
        }
        $datos = json_decode($crudo, true);
        if (!is_array($datos) || !is_array($datos['curso'] ?? null) || !is_string($datos['curso']['titulo'] ?? null)) {
            throw new CursoImportException('curso.json es inválido.');
        }
        $version = $datos['version_formato'] ?? null;
        if (!is_int($version) || $version < 1 || $version > CursoExportService::VERSION_FORMATO) {
            throw new CursoImportException('La versión del formato del archivo no es compatible con esta instalación.');
        }
        return $datos;
    }

    private function crear(array $d, User $usuario): Curso
    {
        $titulo    = $this->tituloLibre($d['titulo']);
        $this->dir = 'cursos/' . Str::slug($titulo) . '-' . Str::lower(Str::random(6));

        // Resuelve marcadores {{archivo:...}} copiando cada archivo una sola vez.
        array_walk_recursive($d, function (&$valor) {
            if (is_string($valor) && str_contains($valor, '{{archivo:')) {
                $valor = $this->resolverMarcadores($valor);
            }
        });

        $curso = Curso::create(array_merge($this->campos($d, Curso::class), [
            'titulo'         => $titulo,
            'estado'         => 'borrador',
            'created_by'     => $usuario->id,
            'orden'          => (int) Curso::max('orden') + 1,
            'categoria_id'   => $this->categoria($d['categoria'] ?? null),
            'imagen_portada' => !empty($d['imagen_portada']) ? $this->copiar($d['imagen_portada']) : null,
        ]));

        foreach ((array) ($d['modulos'] ?? []) as $m) {
            $modulo = $curso->modulos()->create($this->campos($m, Modulo::class));
            foreach ((array) ($m['lecciones'] ?? []) as $l) {
                $leccion = $modulo->lecciones()->create($this->campos($l, Leccion::class));
                foreach ((array) ($l['actividades'] ?? []) as $a) {
                    $this->actividad($leccion, $a);
                }
            }
        }

        return $curso;
    }

    private function actividad(Leccion $leccion, array $a): void
    {
        $actividad = $leccion->actividades()->create($this->campos($a, Actividad::class));

        foreach ((array) ($a['preguntas'] ?? []) as $p) {
            $pregunta = $actividad->preguntas()->create($this->campos($p, Pregunta::class) + [
                'imagen_path' => !empty($p['imagen']) ? $this->copiar($p['imagen']) : null,
            ]);
            foreach ((array) ($p['opciones'] ?? []) as $o) {
                $pregunta->opciones()->create($this->campos($o, Opcion::class));
            }
        }
        foreach ((array) ($a['recursos'] ?? []) as $r) {
            $actividad->recursos()->create($this->campos($r, RecursoActividad::class) + [
                'archivo_path' => !empty($r['archivo']) ? $this->copiar($r['archivo']) : null,
            ]);
        }
        foreach ((array) ($a['rubrica'] ?? []) as $c) {
            $criterio = $actividad->criteriosRubrica()->create($this->campos($c, CriterioRubrica::class));
            foreach ((array) ($c['niveles'] ?? []) as $n) {
                $criterio->niveles()->create($this->campos($n, NivelCriterio::class));
            }
        }
    }

    /** Solo columnas fillable; nunca FKs, ids ni plazos absolutos que vengan en el JSON. */
    private function campos(array $datos, string $clase): array
    {
        /** @var Model $modelo */
        $modelo = new $clase();
        return Arr::except(Arr::only($datos, $modelo->getFillable()), [
            'curso_id', 'modulo_id', 'leccion_id', 'actividad_id', 'pregunta_id', 'criterio_id',
            'created_by', 'categoria_id', 'imagen_portada', 'imagen_path', 'archivo_path',
            'fecha_apertura', 'fecha_cierre',
        ]);
    }

    private function tituloLibre(string $titulo): string
    {
        $base   = Str::limit(trim($titulo), 230, '');
        $nuevo  = "{$base} (importado)";
        $n      = 2;
        while (Curso::withTrashed()->where('titulo', $nuevo)->exists()) {
            $nuevo = "{$base} (importado {$n})";
            $n++;
        }
        return $nuevo;
    }

    private function categoria(?array $c): ?int
    {
        $nombre = trim((string) ($c['nombre'] ?? ''));
        if ($nombre === '') {
            return null;
        }
        $existente = Categoria::whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($nombre)])->first();
        if ($existente) {
            return $existente->id;
        }
        $slug = $base = Str::slug($nombre);
        $n = 2;
        while (Categoria::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }
        $datos = ['nombre' => $nombre, 'slug' => $slug];
        if (isset($c['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $c['color'])) {
            $datos['color'] = $c['color'];
        }
        return Categoria::create($datos)->id;
    }

    private function resolverMarcadores(string $texto): string
    {
        return preg_replace_callback('/\{\{archivo:([^}]+)\}\}/', function (array $m) {
            $nueva = $this->copiar('archivos/' . $m[1]);
            return asset('storage/' . $nueva);
        }, $texto);
    }

    /** Copia una entrada del ZIP al disco `public` (una sola vez por entrada). Devuelve la ruta nueva. */
    private function copiar(string $entrada): string
    {
        if (isset($this->copiados[$entrada])) {
            return $this->copiados[$entrada];
        }
        if (!str_starts_with($entrada, 'archivos/')) {
            throw new CursoImportException("Referencia de archivo no válida: {$entrada}");
        }
        $partes = explode('.', strtolower(basename($entrada)));
        $ext    = count($partes) > 1 ? end($partes) : '';
        if (!in_array($ext, self::EXTENSIONES, true)
            || array_intersect(array_slice($partes, 1), ['php', 'phtml', 'phar'])) {
            throw new CursoImportException('Tipo de archivo no permitido dentro del ZIP: ' . basename($entrada) . '.');
        }
        $flujo = $this->zip->getStream($entrada);
        if ($flujo === false) {
            throw new CursoImportException('Falta el archivo ' . basename($entrada) . ' dentro del ZIP.');
        }
        $nombre = preg_replace('/[^\w.\-]/u', '_', basename($entrada));
        $nueva  = $this->dir . '/' . (count($this->copiados) + 1) . '-' . $nombre;
        $ok = Storage::disk('public')->writeStream($nueva, $flujo);
        fclose($flujo);
        if ($ok === false) {
            throw new \RuntimeException("No se pudo escribir {$nueva}");
        }

        return $this->copiados[$entrada] = $nueva;
    }

    private function limpiar(): void
    {
        foreach ($this->copiados as $ruta) {
            Storage::disk('public')->delete($ruta);
        }
        $this->copiados = [];
    }
}
