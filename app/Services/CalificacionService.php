<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\NivelCriterio;
use App\Models\RespuestaEstudiante;
use Illuminate\Support\Collection;

class CalificacionService
{
    public function tienePreguntasCortas(Actividad $actividad): bool
    {
        return $actividad->preguntas()->where('tipo', 'respuesta_corta')->exists();
    }

    /**
     * Auto-califica un cuestionario. Retorna float en escala 0-puntaje_maximo.
     *
     * Formato JSON de respuestas:
     *   - Opción única / V-F:   { "pregunta_id": "opcion_id" }
     *   - Selección múltiple:   { "pregunta_id": ["id1", "id2"] }
     *   - Respuesta corta:      { "pregunta_id": "texto libre" }
     *
     * $decisionesCortas: [ pregunta_id => true|false ]
     */
    public function calcularCuestionario(
        Actividad $actividad,
        string $respuestaJson,
        array $decisionesCortas = []
    ): float {
        $respuestas = json_decode($respuestaJson, true);
        if (!is_array($respuestas)) {
            return 0.0;
        }

        $totalPuntaje    = 0.0;
        $puntajeObtenido = 0.0;

        foreach ($actividad->preguntas()->with('opciones')->get() as $pregunta) {
            $totalPuntaje += (float) $pregunta->puntaje;
            $respuesta     = $respuestas[$pregunta->id] ?? null;

            if ($respuesta === null) {
                continue;
            }

            if ($pregunta->tipo === 'respuesta_corta') {
                if ($decisionesCortas[$pregunta->id] ?? false) {
                    $puntajeObtenido += (float) $pregunta->puntaje;
                }

            } elseif ($pregunta->seleccion_multiple) {
                $idsCorrectas = $pregunta->opciones
                    ->where('es_correcta', true)
                    ->pluck('id')
                    ->map(fn($id) => (string) $id);

                $nCorrectas = $idsCorrectas->count();
                if ($nCorrectas === 0) continue;

                $seleccionadas = collect(is_array($respuesta) ? $respuesta : [$respuesta])
                    ->map(fn($id) => (string) $id);

                $acertadas = $seleccionadas->intersect($idsCorrectas)->count();
                $puntajeObtenido += ($acertadas / $nCorrectas) * (float) $pregunta->puntaje;

            } else {
                $opcionCorrecta = $pregunta->opciones->firstWhere('es_correcta', true);
                if ($opcionCorrecta && (string) $opcionCorrecta->id === (string) $respuesta) {
                    $puntajeObtenido += (float) $pregunta->puntaje;
                }
            }
        }

        if ($totalPuntaje == 0) {
            return 0.0;
        }

        return round(($puntajeObtenido / $totalPuntaje) * (float) $actividad->puntaje_maximo, 2);
    }

    /**
     * Califica manualmente (ensayo, tarea, practica). Acepta float.
     */
    public function calificarManual(RespuestaEstudiante $respuesta, float $calificacion, ?string $feedback): void
    {
        $respuesta->update([
            'calificacion'       => $calificacion,
            'feedback'           => $feedback,
            'estado'             => 'calificada',
            'fecha_calificacion' => now(),
        ]);
    }

    /**
     * Califica una tarea usando rúbrica.
     * $selecciones: [criterio_id => nivel_criterio_id]
     */
    public function calificarConRubrica(
        RespuestaEstudiante $respuesta,
        array $selecciones,
        ?string $feedback
    ): float {
        foreach ($selecciones as $criterioId => $nivelId) {
            $respuesta->seleccionesRubrica()->updateOrCreate(
                ['criterio_id' => (int) $criterioId],
                ['nivel_criterio_id' => (int) $nivelId]
            );
        }

        $nivelIds     = array_map('intval', array_values($selecciones));
        $calificacion = round(
            (float) NivelCriterio::whereIn('id', $nivelIds)->sum('puntos'),
            2
        );

        $this->calificarManual($respuesta, $calificacion, $feedback);

        return $calificacion;
    }

    /**
     * Dado un conjunto de respuestas (con 'actividad' cargada), devuelve una sola
     * respuesta por cada par (user_id, actividad_id), eligiendo según la política
     * de esa actividad: 'mas_alto' -> mayor calificación, 'ultimo' -> fecha_envio más reciente.
     */
    public function respuestasOficiales(Collection $respuestas): Collection
    {
        return $respuestas
            ->groupBy(fn($r) => $r->user_id . '-' . $r->actividad_id)
            ->map(function ($grupo) {
                $actividad = $grupo->first()->actividad;
                return $actividad->criterio_calificacion_intentos === 'ultimo'
                    ? $grupo->sortByDesc('fecha_envio')->first()
                    : $grupo->sortByDesc('calificacion')->first();
            })
            ->values();
    }

    /**
     * Registra un intento de cuestionario vencido sin envío del estudiante (el tiempo se
     * agotó mientras la pestaña estaba cerrada, por lo que el auto-envío de JS nunca corrió).
     * Usa la misma lógica de calificación que un envío manual, con respuesta vacía.
     */
    public function registrarIntentoExpirado(Actividad $actividad, int $userId): RespuestaEstudiante
    {
        if ($this->tienePreguntasCortas($actividad)) {
            $calificacion = null;
            $estado       = 'en_revision';
        } else {
            $calificacion = $this->calcularCuestionario($actividad, '{}');
            $estado       = 'calificada';
        }

        $respuesta = RespuestaEstudiante::create([
            'user_id'      => $userId,
            'actividad_id' => $actividad->id,
            'respuesta'    => '{}',
            'calificacion' => $calificacion,
            'estado'       => $estado,
            'fecha_envio'  => now(),
        ]);

        $actividad->completarPara($userId);

        return $respuesta;
    }
}
