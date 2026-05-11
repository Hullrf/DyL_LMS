<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\RespuestaEstudiante;

class CalificacionService
{
    /**
     * Indica si un cuestionario tiene preguntas de respuesta corta
     * que requieren revisión manual del instructor.
     */
    public function tienePreguntasCortas(Actividad $actividad): bool
    {
        return $actividad->preguntas()->where('tipo', 'respuesta_corta')->exists();
    }

    /**
     * Auto-califica un cuestionario.
     *
     * Formato del JSON de respuestas:
     *   - Opción única / V-F:    { "pregunta_id": "opcion_id" }
     *   - Selección múltiple:    { "pregunta_id": ["id1", "id2"] }
     *   - Respuesta corta:       { "pregunta_id": "texto libre" }
     *
     * $decisionesCortas — decisiones del instructor para respuestas cortas:
     *   [ pregunta_id => true (correcto) | false (incorrecto) ]
     * Si está vacío, las respuestas cortas valen 0 (pendientes de revisión).
     */
    public function calcularCuestionario(
        Actividad $actividad,
        string $respuestaJson,
        array $decisionesCortas = []
    ): int {
        $respuestas = json_decode($respuestaJson, true);
        if (!is_array($respuestas)) {
            return 0;
        }

        $totalPuntaje    = 0;
        $puntajeObtenido = 0.0;

        foreach ($actividad->preguntas()->with('opciones')->get() as $pregunta) {
            $totalPuntaje += $pregunta->puntaje;
            $respuesta     = $respuestas[$pregunta->id] ?? null;

            if ($respuesta === null) {
                continue;
            }

            if ($pregunta->tipo === 'respuesta_corta') {
                // Solo suma si el instructor la marcó como correcta
                if ($decisionesCortas[$pregunta->id] ?? false) {
                    $puntajeObtenido += $pregunta->puntaje;
                }

            } elseif ($pregunta->seleccion_multiple) {
                // Selección múltiple: puntaje parcial por respuesta correcta
                $idsCorrectas = $pregunta->opciones
                    ->where('es_correcta', true)
                    ->pluck('id')
                    ->map(fn($id) => (string) $id);

                $nCorrectas = $idsCorrectas->count();
                if ($nCorrectas === 0) continue;

                $seleccionadas = collect(is_array($respuesta) ? $respuesta : [$respuesta])
                    ->map(fn($id) => (string) $id);

                $acertadas = $seleccionadas->intersect($idsCorrectas)->count();
                $puntajeObtenido += ($acertadas / $nCorrectas) * $pregunta->puntaje;

            } else {
                // Opción única / verdadero-falso
                $opcionCorrecta = $pregunta->opciones->firstWhere('es_correcta', true);
                if ($opcionCorrecta && (string) $opcionCorrecta->id === (string) $respuesta) {
                    $puntajeObtenido += $pregunta->puntaje;
                }
            }
        }

        if ($totalPuntaje === 0) {
            return 0;
        }

        return (int) round(($puntajeObtenido / $totalPuntaje) * $actividad->puntaje_maximo);
    }

    /**
     * Califica manualmente una respuesta (ensayo, tarea, practica).
     */
    public function calificarManual(RespuestaEstudiante $respuesta, int $calificacion, ?string $feedback): void
    {
        $respuesta->update([
            'calificacion'       => $calificacion,
            'feedback'           => $feedback,
            'estado'             => 'calificada',
            'fecha_calificacion' => now(),
        ]);
    }
}
