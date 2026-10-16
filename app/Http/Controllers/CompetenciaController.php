<?php

namespace App\Http\Controllers;

use App\Models\Competencia;
use App\Models\Curso;
use Illuminate\Http\Request;

class CompetenciaController extends Controller
{
    public function index(Curso $curso)
    {
        $this->authorize('update', $curso);
        $competencias = $curso->competencias()->with('actividades')->get();
        $actividades  = $curso->todasLasActividades();

        return view('cursos.competencias', compact('curso', 'competencias', 'actividades'));
    }

    public function store(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);
        [$descripcion, $ids] = $this->validar($request, $curso);

        $competencia = $curso->competencias()->create([
            'descripcion' => $descripcion,
            'orden'       => (int) $curso->competencias()->max('orden') + 1,
        ]);
        $competencia->actividades()->sync($ids);

        return redirect()->route('cursos.competencias.index', $curso)->with('success', 'Competencia agregada.');
    }

    public function update(Request $request, Curso $curso, Competencia $competencia)
    {
        $this->authorize('update', $curso);
        abort_unless($competencia->curso_id === $curso->id, 404);
        [$descripcion, $ids] = $this->validar($request, $curso);

        $competencia->update(['descripcion' => $descripcion]);
        $competencia->actividades()->sync($ids);

        return redirect()->route('cursos.competencias.index', $curso)->with('success', 'Competencia actualizada.');
    }

    public function destroy(Curso $curso, Competencia $competencia)
    {
        $this->authorize('update', $curso);
        abort_unless($competencia->curso_id === $curso->id, 404);
        $competencia->delete();

        return redirect()->route('cursos.competencias.index', $curso)->with('success', 'Competencia eliminada.');
    }

    /** @return array{0: string, 1: array<int>} descripción y ids de actividades que SÍ son de este curso */
    private function validar(Request $request, Curso $curso): array
    {
        $d = $request->validate([
            'descripcion'     => 'required|string|max:500',
            'actividad_ids'   => 'nullable|array',
            'actividad_ids.*' => 'integer',
        ]);

        $validos = $curso->todasLasActividades()->pluck('id')->all();
        $ids     = array_values(array_intersect($d['actividad_ids'] ?? [], $validos));

        return [$d['descripcion'], $ids];
    }
}
