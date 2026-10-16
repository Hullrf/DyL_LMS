<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\ProgresoActividad;
use App\Models\ProgresoLeccion;
use App\Models\RespuestaEstudiante;
use Illuminate\Support\Collection;

class SeguimientoService
{
    public function __construct(private NotaCursoService $notas, private CalificacionService $calificaciones) {}

    public function para(Curso $curso): array
    {
        $diasSinAcceso = (int) ($curso->seguimiento_dias_sin_acceso ?? 7);
        $maxAtrasadas  = (int) ($curso->seguimiento_max_atrasadas ?? 2);
        $aprobatoria   = (int) ($curso->nota_aprobatoria ?? 80);

        $inscripciones = Inscripcion::with('usuario')->where('curso_id', $curso->id)
            ->where('estado', '!=', 'abandonado')->get()->filter(fn($i) => $i->usuario);
        $userIds = $inscripciones->pluck('user_id');

        $leccionIds     = $curso->lecciones()->pluck('lecciones.id');
        $totalLecciones = $leccionIds->count();
        $completadas    = $totalLecciones === 0 ? collect() : ProgresoLeccion::whereIn('user_id', $userIds)
            ->whereIn('leccion_id', $leccionIds)->where('completado', true)
            ->selectRaw('user_id, count(*) as n')->groupBy('user_id')->pluck('n', 'user_id');

        $todas        = $curso->todasLasActividades();
        $calificables = $todas->filter(fn($a) => $a->tieneCalificacion())->values();
        $vencidasIds  = $todas->filter(fn($a) => $a->fecha_cierre && $a->fecha_cierre->lt(now()))->pluck('id');

        $clave      = fn($r) => "{$r->user_id}-{$r->actividad_id}";
        $entregadas = RespuestaEstudiante::whereIn('user_id', $userIds)->whereIn('actividad_id', $vencidasIds)
            ->get(['user_id', 'actividad_id'])->toBase()->map($clave)
            ->merge(ProgresoActividad::whereIn('user_id', $userIds)->whereIn('actividad_id', $vencidasIds)
                ->where('completado', true)->get(['user_id', 'actividad_id'])->toBase()->map($clave))
            ->flip();

        $respuestas = RespuestaEstudiante::whereIn('user_id', $userIds)->whereIn('actividad_id', $calificables->pluck('id'))
            ->with('actividad')->get()->groupBy('user_id');

        $filas = $inscripciones->map(function ($insc) use (
            $curso, $calificables, $vencidasIds, $entregadas, $respuestas, $completadas, $totalLecciones,
            $diasSinAcceso, $maxAtrasadas, $aprobatoria
        ) {
            $u    = $insc->usuario;
            $mias = $respuestas->get($u->id, collect());
            $nota = $this->notas->calcular(
                $calificables,
                $this->calificaciones->respuestasOficiales($mias->where('estado', 'calificada'))->keyBy('actividad_id'),
                $aprobatoria,
                $mias->pluck('actividad_id')->flip(),
            );

            $atrasadas = $vencidasIds->filter(fn($id) => !isset($entregadas["{$u->id}-{$id}"]))->count();
            $senales = array_keys(array_filter([
                'sin_acceso' => $insc->estado !== 'completado'
                    && ($u->ultimo_acceso === null || $u->ultimo_acceso->lt(now()->subDays($diasSinAcceso))),
                'atrasadas'  => $atrasadas >= $maxAtrasadas,
                'nota_baja'  => !$nota['alcanzable']
                    || ($nota['promedio_a_la_fecha'] !== null && $nota['promedio_a_la_fecha'] < $aprobatoria),
            ]));

            return (object) [
                'usuario'       => $u,
                'progreso_pct'  => $totalLecciones > 0 ? (int) round(($completadas[$u->id] ?? 0) / $totalLecciones * 100) : null,
                'promedio'      => $nota['promedio_a_la_fecha'],
                'atrasadas'     => $atrasadas,
                'ultimo_acceso' => $u->ultimo_acceso,
                'senales'       => $senales,
                'nivel'         => match (count($senales)) { 0 => 'ok', 1 => 'atencion', default => 'riesgo' },
            ];
        })->sortBy(fn($f) => [['ok' => 2, 'atencion' => 1, 'riesgo' => 0][$f->nivel], $f->usuario->name])->values();

        return [
            'filas'    => $filas,
            'resumen'  => [
                'ok'       => $filas->where('nivel', 'ok')->count(),
                'atencion' => $filas->where('nivel', 'atencion')->count(),
                'riesgo'   => $filas->where('nivel', 'riesgo')->count(),
            ],
            'umbrales' => ['dias_sin_acceso' => $diasSinAcceso, 'max_atrasadas' => $maxAtrasadas],
        ];
    }
}
