<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\IntentoEnProgreso;
use App\Models\ProgresoActividad;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LineaDeTiempoService
{
    public const MAX_ATRASADAS = 50;

    /** @return array{atrasadas: Collection, dias: Collection} */
    public function para(
        User $user,
        int $dias = 7,
        ?int $cursoId = null,
        bool $incluirEntregadas = false,
        ?string $buscar = null,
        ?int $ronda = null,
    ): array {
        $entregadas = $this->idsEntregados($user);
        $enProgreso = IntentoEnProgreso::where('user_id', $user->id)->pluck('actividad_id')->flip();

        $base = fn() => $this->actividadesVisibles($user, $cursoId, $buscar, $ronda);

        $proximas = $base()
            ->whereBetween('fecha_cierre', [now(), now()->addDays($dias)->endOfDay()])
            ->orderBy('fecha_cierre')->get()
            ->map(fn($a) => $this->item($a, $entregadas, $enProgreso))
            ->when(!$incluirEntregadas, fn($c) => $c->reject(fn($i) => $i->estado === 'entregada'));

        $atrasadas = $base()
            ->where('fecha_cierre', '<', now())
            ->whereNotIn('id', $entregadas->keys()->all())
            ->orderByDesc('fecha_cierre')->limit(self::MAX_ATRASADAS)->get()
            ->map(fn($a) => $this->item($a, $entregadas, $enProgreso));

        return [
            'atrasadas' => $atrasadas,
            'dias'      => $proximas->groupBy(fn($i) => $i->actividad->fecha_cierre->format('Y-m-d'))->sortKeys(),
        ];
    }

    /** curso_id => fecha de cierre más cercana (futura) de una actividad aún no entregada. */
    public function proximaEntregaPorCurso(User $user): Collection
    {
        $entregadas = $this->idsEntregados($user);

        return $this->actividadesVisibles($user)
            ->where('fecha_cierre', '>=', now())
            ->orderBy('fecha_cierre')->get()
            ->reject(fn($a) => isset($entregadas[$a->id]))
            ->groupBy(fn($a) => $a->leccion->modulo->curso_id)
            ->map(fn($g) => $g->first()->fecha_cierre);
    }

    private function actividadesVisibles(User $user, ?int $cursoId = null, ?string $buscar = null, ?int $ronda = null): Builder
    {
        return Actividad::query()
            ->with('leccion.modulo.curso')
            ->whereNotNull('fecha_cierre')
            ->whereHas('leccion.modulo.curso', function (Builder $q) use ($user, $cursoId) {
                $q->where('estado', 'publicado')
                  ->whereHas('inscripciones', fn($i) => $i->where('user_id', $user->id)->where('estado', '!=', 'abandonado'))
                  ->when($cursoId, fn($q2) => $q2->where('cursos.id', $cursoId));
            })
            ->when($buscar, fn($q) => $q->where(fn($w) => $w
                ->where('titulo', 'like', "%{$buscar}%")->orWhere('tipo', 'like', "%{$buscar}%")))
            ->when($ronda, fn($q) => $q->where('ronda', $ronda));
    }

    /** Collection flip: actividad_id => índice (isset() para chequear). */
    private function idsEntregados(User $user): Collection
    {
        return RespuestaEstudiante::where('user_id', $user->id)->pluck('actividad_id')
            ->merge(ProgresoActividad::where('user_id', $user->id)->where('completado', true)->pluck('actividad_id'))
            ->flip();
    }

    private function item(Actividad $a, Collection $entregadas, Collection $enProgreso): \stdClass
    {
        $estado = isset($entregadas[$a->id]) ? 'entregada' : (isset($enProgreso[$a->id]) ? 'en_progreso' : 'pendiente');

        return (object) [
            'actividad' => $a,
            'curso'     => $a->leccion->modulo->curso,
            'estado'    => $estado,
            'accion'    => match (true) {
                $estado === 'entregada'   => 'Ver',
                $estado === 'en_progreso' => 'Continuar',
                in_array($a->tipo, Actividad::TIPOS_CON_ENTREGA_ARCHIVO, true) => 'Entregar',
                default                   => 'Responder',
            },
        ];
    }
}
