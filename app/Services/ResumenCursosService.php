<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\ProgresoLeccion;
use App\Models\User;
use Illuminate\Support\Collection;

class ResumenCursosService
{
    public function __construct(private LineaDeTiempoService $lineaDeTiempo) {}

    /** @return array<int, array{porcentaje: ?int, proxima: ?\Illuminate\Support\Carbon}> */
    public function para(User $user, Collection $cursos): array
    {
        $ids = $cursos->pluck('id');

        $totales = Curso::whereIn('id', $ids)->withCount('lecciones')->pluck('lecciones_count', 'id');

        $completadas = ProgresoLeccion::where('user_id', $user->id)->where('completado', true)
            ->with('leccion.modulo:id,curso_id')->get()
            ->filter(fn($p) => $p->leccion?->modulo && $ids->contains($p->leccion->modulo->curso_id))
            ->countBy(fn($p) => $p->leccion->modulo->curso_id);

        $proximas = $this->lineaDeTiempo->proximaEntregaPorCurso($user);

        return $cursos->mapWithKeys(function ($curso) use ($totales, $completadas, $proximas) {
            $total = (int) ($totales[$curso->id] ?? 0);

            return [$curso->id => [
                'porcentaje' => $total > 0 ? (int) round((($completadas[$curso->id] ?? 0) / $total) * 100) : null,
                'proxima'    => $proximas[$curso->id] ?? null,
            ]];
        })->all();
    }
}
