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
        $conEnvio = $respuestas->pluck('actividad_id')->flip();

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
                'nota'      => $nota,
                'max'       => $max,
                'pct'       => $pct,
                'aporta'    => $pct === null ? null : $peso * $pct / 100,
                'estado'    => $oficial ? 'calificada' : (isset($conEnvio[$a->id]) ? 'en_revision' : 'pendiente'),
            ];
        })->values();

        $calificadas   = $filas->where('estado', 'calificada');
        $acumulado     = (float) $calificadas->sum('aporta');
        $pesoCalif     = (float) $calificadas->sum('peso');
        $pesoTotal     = (float) $filas->sum('peso');
        $pesoPendiente = $pesoTotal - $pesoCalif;
        $aprobatoria   = (int) ($curso->nota_aprobatoria ?? 80);

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
