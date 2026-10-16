<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Support\Collection;

class NotaCursoService
{
    public function __construct(private CalificacionService $calificaciones) {}

    public function para(User $user, Curso $curso): array
    {
        $actividades = $curso->actividadesCalificables();
        $ids         = $actividades->pluck('id');

        $respuestas = RespuestaEstudiante::where('user_id', $user->id)
            ->whereIn('actividad_id', $ids)->with('actividad')->get();

        $oficiales = $this->calificaciones
            ->respuestasOficiales($respuestas->where('estado', 'calificada'))
            ->keyBy('actividad_id');

        return $this->calcular($actividades, $oficiales, (int) ($curso->nota_aprobatoria ?? 80), $respuestas->pluck('actividad_id')->flip());
    }

    /**
     * Cálculo puro (sin consultas). $actividades = TODAS las calificables del curso (define
     * modo y pesos); $oficiales = respuesta oficial calificada por actividad_id; $conEnvio =
     * actividad_id => algo, para distinguir en_revision de pendiente; $soloIds restringe las
     * filas (p. ej. un módulo) conservando los pesos del curso completo.
     */
    public function calcular(Collection $actividades, Collection $oficiales, ?int $aprobatoria = null, ?Collection $conEnvio = null, ?Collection $soloIds = null): array
    {
        $conEnvio ??= collect();
        $modo        = $this->esPonderado($actividades) ? 'ponderado' : 'por_puntos';
        $totalPuntos = (float) $actividades->sum(fn($a) => (float) $a->puntaje_maximo);

        $filas = $actividades->map(function ($a) use ($modo, $totalPuntos, $oficiales, $conEnvio) {
            $max  = (float) $a->puntaje_maximo;
            $peso = $modo === 'ponderado'
                ? (float) $a->ponderacion
                : ($totalPuntos > 0 ? $max / $totalPuntos * 100 : 0.0);

            $oficial = $oficiales->get($a->id);
            $nota    = $oficial ? (float) $oficial->calificacion : null;
            $pct     = $oficial && $max > 0 ? $nota / $max * 100 : ($oficial ? 0.0 : null);

            return (object) [
                'actividad' => $a,
                'peso'      => round($peso, 2),
                'peso_real' => $peso, // sin redondear: las sumas usan este
                'nota'      => $nota,
                'max'       => $max,
                'pct'       => $pct,
                'aporta'    => $pct === null ? null : $peso * $pct / 100,
                'estado'    => $oficial ? 'calificada' : (isset($conEnvio[$a->id]) ? 'en_revision' : 'pendiente'),
            ];
        })->values();
        if ($soloIds) {
            $filas = $filas->filter(fn($f) => $soloIds->contains($f->actividad->id))->values();
        }

        $calificadas   = $filas->where('estado', 'calificada');
        $acumulado     = (float) $calificadas->sum('aporta');
        $pesoCalif     = (float) $calificadas->sum('peso_real');
        $pesoTotal     = (float) $filas->sum('peso_real');
        $pesoPendiente = $pesoTotal - $pesoCalif;
        $aprobatoria   ??= 80;

        $requerido = null;
        if ($pesoPendiente > 0.0001) {
            $faltante  = $aprobatoria - $acumulado;
            $requerido = $faltante <= 0 ? 0.0 : round($faltante / $pesoPendiente * 100, 1);
        }

        return [
            'filas'               => $filas,
            'modo'                => $modo,
            'acumulado'           => round($acumulado, 2),
            'peso_calificado'     => round($pesoCalif, 2),
            'peso_total'          => round($pesoTotal, 2),
            'promedio_a_la_fecha' => $pesoCalif > 0 ? (int) round($acumulado / $pesoCalif * 100) : null,
            'aprobatoria'         => $aprobatoria,
            'requerido'           => $requerido,
            'alcanzable'          => $requerido === null || $requerido <= 100,
        ];
    }

    /** Todas con peso y suma 100 (tolerancia de centésimas). */
    private function esPonderado(Collection $actividades): bool
    {
        if ($actividades->isEmpty() || $actividades->contains(fn($a) => $a->ponderacion === null)) {
            return false;
        }

        return abs((float) $actividades->sum(fn($a) => (float) $a->ponderacion) - 100) < 0.01;
    }
}
