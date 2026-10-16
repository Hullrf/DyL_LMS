<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FichaController extends Controller
{
    public function edit(Curso $curso)
    {
        $this->authorize('update', $curso);
        $curso->load('ficha', 'bibliografias');

        return view('cursos.ficha', compact('curso'));
    }

    public function update(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $d = $request->validate([
            'justificacion'          => 'nullable|string|max:5000',
            'resultado_aprendizaje'  => 'nullable|string|max:2000',
            'resultados_especificos' => 'nullable|string|max:5000',
            'creditos'               => 'nullable|integer|min:1|max:20',
            'semestre'               => 'nullable|integer|min:1|max:20',
            'prerrequisitos'         => 'nullable|string|max:2000',
            'programa'               => 'nullable|string|max:255',
            'facultad'               => 'nullable|string|max:255',
            'bibliografia'           => 'nullable|array|max:50',
            'bibliografia.*.autor'     => 'required|string|max:255',
            'bibliografia.*.anio'      => 'nullable|integer|min:1900|max:2100',
            'bibliografia.*.titulo'    => 'required|string|max:255',
            'bibliografia.*.editorial' => 'nullable|string|max:255',
        ]);

        $especificos = collect(preg_split('/\R/u', (string) ($d['resultados_especificos'] ?? '')))
            ->map(fn($l) => trim($l))->filter()->values()->all();

        DB::transaction(function () use ($curso, $d, $especificos) {
            $curso->ficha()->updateOrCreate([], [
                'justificacion'          => $d['justificacion'] ?? null,
                'resultado_aprendizaje'  => $d['resultado_aprendizaje'] ?? null,
                'resultados_especificos' => $especificos ?: null,
                'creditos'               => $d['creditos'] ?? null,
                'semestre'               => $d['semestre'] ?? null,
                'prerrequisitos'         => $d['prerrequisitos'] ?? null,
                'programa'               => $d['programa'] ?? null,
                'facultad'               => $d['facultad'] ?? null,
            ]);

            $curso->bibliografias()->delete();
            foreach (array_values($d['bibliografia'] ?? []) as $i => $b) {
                $curso->bibliografias()->create([
                    'autor' => $b['autor'], 'anio' => $b['anio'] ?? null, 'titulo' => $b['titulo'],
                    'editorial' => $b['editorial'] ?? null, 'orden' => $i,
                ]);
            }
        });

        return redirect()->route('cursos.ficha.edit', $curso)->with('success', 'Ficha académica guardada.');
    }
}
