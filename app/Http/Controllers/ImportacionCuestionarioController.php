<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportacionCuestionarioController extends Controller
{
    public function store(Request $request, Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);
        abort_unless($actividad->tipo === 'cuestionario', 403);

        $request->validate([
            'archivo' => 'required|file|mimes:json|max:2048',
        ]);

        $contenido = json_decode($request->file('archivo')->get(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['archivo' => 'El archivo no tiene un formato JSON válido.']);
        }

        $validator = Validator::make((array) $contenido, [
            'version'                         => 'required|integer|in:1',
            'preguntas'                       => 'required|array|min:1',
            'preguntas.*.texto'               => 'required|string',
            'preguntas.*.tipo'                => 'required|in:opcion_multiple,respuesta_corta',
            'preguntas.*.multiple'            => 'nullable|boolean',
            'preguntas.*.opciones'            => 'required_if:preguntas.*.tipo,opcion_multiple|array|min:2',
            'preguntas.*.opciones.*.texto'    => 'required|string',
            'preguntas.*.opciones.*.correcta' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors(['archivo' => 'El archivo no tiene la estructura esperada: ' . $validator->errors()->first()]);
        }

        $preguntasJson = $contenido['preguntas'];
        $pendientes    = 0;

        DB::transaction(function () use ($actividad, $preguntasJson, &$pendientes) {
            $orden = $actividad->preguntas()->max('orden') + 1;

            foreach ($preguntasJson as $item) {
                $esOpcionMultiple = $item['tipo'] === 'opcion_multiple';
                $opciones         = $item['opciones'] ?? [];
                $esVerdaderoFalso = $esOpcionMultiple && $this->esVerdaderoFalso($opciones);

                $pregunta = $actividad->preguntas()->create([
                    'pregunta_texto'     => $item['texto'],
                    'tipo'               => $esVerdaderoFalso ? 'verdadero_falso' : $item['tipo'],
                    'seleccion_multiple' => $esOpcionMultiple && !$esVerdaderoFalso && !empty($item['multiple']),
                    'puntaje'            => 1,
                    'orden'              => $orden++,
                ]);

                if ($esOpcionMultiple) {
                    $tieneCorrecta = false;
                    foreach ($opciones as $i => $opcionItem) {
                        $correcta      = (bool) ($opcionItem['correcta'] ?? false);
                        $tieneCorrecta = $tieneCorrecta || $correcta;
                        $pregunta->opciones()->create([
                            'texto'       => $opcionItem['texto'],
                            'es_correcta' => $correcta,
                            'orden'       => $i + 1,
                        ]);
                    }
                    if (!$tieneCorrecta) $pendientes++;
                }
            }

            $actividad->redistribuirPuntajesPreguntas();
        });

        $mensaje = count($preguntasJson) . ' preguntas importadas.';
        if ($pendientes > 0) {
            $mensaje .= " {$pendientes} necesita" . ($pendientes === 1 ? '' : 'n') . ' que marques la respuesta correcta.';
        }

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', $mensaje);
    }

    public function ejemplo()
    {
        $contenido = [
            'version' => 1,
            'preguntas' => [
                [
                    'texto' => '¿Cuál es la capital de Francia?',
                    'tipo' => 'opcion_multiple',
                    'multiple' => false,
                    'opciones' => [
                        ['texto' => 'Madrid', 'correcta' => false],
                        ['texto' => 'París', 'correcta' => true],
                        ['texto' => 'Roma', 'correcta' => false],
                    ],
                ],
                [
                    'texto' => 'Selecciona los lenguajes de programación (puede haber varias correctas)',
                    'tipo' => 'opcion_multiple',
                    'multiple' => true,
                    'opciones' => [
                        ['texto' => 'PHP', 'correcta' => true],
                        ['texto' => 'Photoshop', 'correcta' => false],
                        ['texto' => 'JavaScript', 'correcta' => true],
                    ],
                ],
                [
                    'texto' => 'El sol es una estrella',
                    'tipo' => 'opcion_multiple',
                    'multiple' => false,
                    'opciones' => [
                        ['texto' => 'Verdadero', 'correcta' => true],
                        ['texto' => 'Falso', 'correcta' => false],
                    ],
                ],
                [
                    'texto' => 'Explica brevemente qué es la fotosíntesis',
                    'tipo' => 'respuesta_corta',
                ],
            ],
        ];

        return response()->streamDownload(
            fn () => print(json_encode($contenido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'ejemplo-cuestionario.json',
            ['Content-Type' => 'application/json']
        );
    }

    private function esVerdaderoFalso(array $opciones): bool
    {
        if (count($opciones) !== 2) return false;

        $normalizados = collect($opciones)
            ->map(fn($o) => Str::of($o['texto'] ?? '')->lower()->ascii()->trim()->toString())
            ->sort()
            ->values()
            ->toArray();

        return $normalizados === ['falso', 'verdadero'] || $normalizados === ['false', 'true'];
    }
}
