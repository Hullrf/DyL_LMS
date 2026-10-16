<?php

namespace App\Services;

use App\Models\EventoPersonal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarioService
{
    public function __construct(private LineaDeTiempoService $lineaDeTiempo) {}

    /** @return Collection<int, \stdClass> ordenada por inicio */
    public function eventos(User $user, Carbon $desde, Carbon $hasta, ?int $cursoId = null): Collection
    {
        $items = collect();

        foreach ($this->lineaDeTiempo->actividadesEnRango($user, $desde, $hasta, $cursoId) as $a) {
            $curso = $a->leccion->modulo->curso;

            foreach (['apertura' => 'Abre', 'cierre' => 'Vence'] as $tipo => $verbo) {
                $f = $a->{"fecha_{$tipo}"};
                if ($f && $f->between($desde, $hasta)) {
                    $items->push((object) [
                        'uid'     => "act-{$a->id}-{$tipo}",
                        'id'      => null,
                        'tipo'    => $tipo,
                        'inicia'  => $f,
                        'fin'     => $f->copy()->addMinutes(30),
                        'titulo'  => "{$verbo}: {$a->tituloCompleto()}",
                        'detalle' => $curso->titulo,
                        'url'     => route('actividades.show', $a),
                    ]);
                }
            }
        }

        if (!$cursoId) {
            EventoPersonal::where('user_id', $user->id)->whereBetween('inicia_en', [$desde, $hasta])->get()
                ->each(fn($e) => $items->push((object) [
                    'uid'     => "evt-{$e->id}",
                    'id'      => $e->id,
                    'tipo'    => 'personal',
                    'inicia'  => $e->inicia_en,
                    'fin'     => $e->termina_en ?? $e->inicia_en->copy()->addHour(),
                    'titulo'  => $e->titulo,
                    'detalle' => $e->descripcion,
                    'url'     => null,
                ]));
        }

        return $items->sortBy('inicia')->values();
    }
}
