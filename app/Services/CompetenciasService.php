<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Support\Collection;

class CompetenciasService
{
    public function __construct(private LineaDeTiempoService $lineaDeTiempo, private NotaCursoService $notas) {}

    /** @return Collection<int, \stdClass> {competencia, total, cumplidas, nota_media} */
    public function avance(User $user, Curso $curso): Collection
    {
        $competencias = $curso->competencias()->with('actividades')->get();
        if ($competencias->isEmpty()) {
            return collect();
        }

        $entregadas = $this->lineaDeTiempo->idsEntregados($user);
        $pctPorActividad = $this->notas->para($user, $curso)['filas']
            ->filter(fn($f) => $f->pct !== null)->mapWithKeys(fn($f) => [$f->actividad->id => $f->pct]);

        return $competencias->map(function ($c) use ($entregadas, $pctPorActividad) {
            $ids       = $c->actividades->pluck('id');
            $calificas = $ids->filter(fn($id) => $pctPorActividad->has($id));

            return (object) [
                'competencia' => $c,
                'total'       => $ids->count(),
                'cumplidas'   => $ids->filter(fn($id) => isset($entregadas[$id]))->count(),
                'nota_media'  => $calificas->isEmpty() ? null : (int) round($calificas->avg(fn($id) => $pctPorActividad[$id])),
            ];
        })->values();
    }
}
