<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuloController extends Controller
{
    public function store(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $validated = $request->validate([
            'titulo'      => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $orden = $curso->modulos()->max('orden') + 1;

        $curso->modulos()->create([
            'titulo'      => $validated['titulo'],
            'descripcion' => $validated['descripcion'] ?? null,
            'orden'       => $orden,
        ]);

        return redirect()
            ->route('cursos.edit', $curso)
            ->with('success', 'Módulo agregado correctamente');
    }

    public function edit(Modulo $modulo)
    {
        $this->authorize('update', $modulo->curso);
        return view('modulos.edit', compact('modulo'));
    }

    public function update(Request $request, Modulo $modulo)
    {
        $this->authorize('update', $modulo->curso);

        $validated = $request->validate([
            'titulo'          => 'required|string|max:255',
            'descripcion'     => 'nullable|string',
            'duracion_horas'  => 'nullable|integer|min:0',
        ]);

        $modulo->update($validated);

        return redirect()
            ->route('cursos.edit', $modulo->curso)
            ->with('success', 'Módulo actualizado correctamente');
    }

    public function destroy(Modulo $modulo)
    {
        $curso = $modulo->curso;
        $this->authorize('update', $curso);
        $modulo->delete();

        return redirect()
            ->route('cursos.edit', $curso)
            ->with('success', 'Módulo eliminado correctamente');
    }

    public function reordenar(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $request->validate(['orden' => 'required|array']);

        foreach ($request->orden as $index => $moduloId) {
            Modulo::where('id', $moduloId)
                ->where('curso_id', $curso->id)
                ->update(['orden' => $index]);
        }

        return response()->json(['ok' => true]);
    }
}
