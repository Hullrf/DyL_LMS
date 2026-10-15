<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PonderacionController extends Controller
{
    public function edit(Curso $curso)
    {
        $this->authorize('update', $curso);
        $actividades = $curso->actividadesCalificables();

        return view('cursos.ponderaciones', compact('curso', 'actividades'));
    }

    public function update(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);
        $actividades = $curso->actividadesCalificables();

        $request->validate([
            'ponderaciones'   => 'required|array',
            'ponderaciones.*' => 'nullable|numeric|min:0|max:100',
        ]);

        $valores = $actividades->mapWithKeys(function ($a) use ($request) {
            $v = $request->input("ponderaciones.{$a->id}");
            return [$a->id => ($v === null || $v === '') ? null : round((float) $v, 2)];
        });

        $conValor = $valores->filter(fn($v) => $v !== null);

        if ($conValor->isNotEmpty()) {
            if ($conValor->count() !== $valores->count()) {
                return back()->withInput()->withErrors([
                    'ponderaciones' => 'Asigna un peso a todas las actividades con nota, o deja todas vacías para repartir por puntaje.',
                ]);
            }
            if (abs($conValor->sum() - 100) >= 0.01) {
                return back()->withInput()->withErrors([
                    'ponderaciones' => 'Las ponderaciones deben sumar 100 % (hoy suman ' . rtrim(rtrim(number_format($conValor->sum(), 2), '0'), '.') . ' %).',
                ]);
            }
        }

        DB::transaction(function () use ($actividades, $valores) {
            foreach ($actividades as $a) {
                $a->update(['ponderacion' => $valores[$a->id]]);
            }
        });

        return redirect()->route('cursos.ponderaciones.edit', $curso)->with('success', 'Ponderaciones guardadas.');
    }
}
