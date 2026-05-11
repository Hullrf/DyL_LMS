<?php

namespace App\Http\Controllers;

use App\Exports\EjemploRubricaExport;
use App\Models\Actividad;
use App\Services\RubricaImportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RubricaController extends Controller
{
    public function __construct(private RubricaImportService $importService) {}

    /**
     * Guarda o reemplaza la rúbrica de una actividad.
     * Recibe criterios_json (string JSON) + usa_rubrica (bool).
     */
    public function store(Request $request, Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);

        // Parsear criterios desde JSON
        if ($request->has('criterios_json')) {
            $criteriosDecoded = json_decode($request->criterios_json, true);
            $request->merge(['criterios' => is_array($criteriosDecoded) ? $criteriosDecoded : []]);
        }

        $request->validate([
            'usa_rubrica'                            => 'required|boolean',
            'criterios'                              => 'nullable|array',
            'criterios.*.nombre'                     => 'required_with:criterios|string|max:255',
            'criterios.*.niveles'                    => 'required_with:criterios|array|min:1',
            'criterios.*.niveles.*.descripcion'      => 'required|string',
            'criterios.*.niveles.*.puntos'           => 'required|numeric|min:0|max:99.99',
        ]);

        $actividad->update(['usa_rubrica' => $request->boolean('usa_rubrica')]);

        // Eliminar criterios anteriores (cascade borra niveles)
        $actividad->criteriosRubrica()->delete();

        if ($request->boolean('usa_rubrica') && !empty($request->criterios)) {
            foreach ($request->criterios as $i => $criterioData) {
                $criterio = $actividad->criteriosRubrica()->create([
                    'nombre' => $criterioData['nombre'],
                    'orden'  => $i,
                ]);

                foreach ($criterioData['niveles'] as $j => $nivelData) {
                    $criterio->niveles()->create([
                        'descripcion' => $nivelData['descripcion'],
                        'puntos'      => $nivelData['puntos'],
                        'orden'       => $j,
                    ]);
                }
            }

            // Actualizar puntaje_maximo = suma de nivel máximo por criterio
            $puntajeMax = $actividad->fresh()->puntajeRubrica();
            $actividad->update(['puntaje_maximo' => $puntajeMax ?: 5.00]);
        }

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Rúbrica guardada correctamente.');
    }

    /**
     * Descarga el archivo Excel de ejemplo con instrucciones.
     */
    public function ejemplo()
    {
        return Excel::download(new EjemploRubricaExport(), 'ejemplo-rubrica.xlsx');
    }

    /**
     * Parsea un Excel subido y retorna los criterios como JSON.
     * NO guarda en BD — el frontend los carga en el builder para revisión.
     */
    public function importar(Request $request, Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $criterios = $this->importService->parsear($request->file('archivo'));

            if (empty($criterios)) {
                return response()->json([
                    'error' => 'No se encontraron criterios en el archivo. Verifica que usas la plantilla correcta (pestaña "Rúbrica", primera fila = encabezados).',
                ], 422);
            }

            return response()->json(['criterios' => $criterios]);

        } catch (\Throwable $e) {
            \Log::error('RubricaImport error: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'error' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 422);
        }
    }
}
