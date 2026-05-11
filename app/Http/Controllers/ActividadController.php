<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Leccion;
use Illuminate\Http\Request;

class ActividadController extends Controller
{
    public function create(Leccion $leccion)
    {
        $this->authorize('update', $leccion->modulo->curso);
        return view('actividades.create', compact('leccion'));
    }

    public function store(Request $request, Leccion $leccion)
    {
        $this->authorize('update', $leccion->modulo->curso);

        $validated = $request->validate([
            'titulo'           => 'required|string|max:255',
            'tipo'             => 'required|in:cuestionario,ensayo,tarea,practica',
            'descripcion'      => 'nullable|string',
            'puntaje_maximo'   => 'required|integer|min:1|max:1000',
            'duracion_minutos' => 'nullable|integer|min:1',
            'es_obligatoria'   => 'boolean',
        ]);

        $orden = $leccion->actividades()->max('orden') + 1;

        $actividad = $leccion->actividades()->create([
            ...$validated,
            'es_obligatoria' => $request->boolean('es_obligatoria', true),
            'orden'          => $orden,
        ]);

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Actividad creada. Ahora configura su contenido.');
    }

    public function show(Actividad $actividad)
    {
        $this->authorize('view', $actividad->leccion->modulo->curso);
        $respuesta = $actividad->respuestas()
            ->where('user_id', auth()->id())
            ->with('seleccionesRubrica')
            ->latest()
            ->first();

        $criteriosRubrica = $actividad->usa_rubrica
            ? $actividad->criteriosRubrica()->with('niveles')->get()
            : collect();

        $seleccionesMap = $respuesta
            ? $respuesta->seleccionesRubrica->pluck('nivel_criterio_id', 'criterio_id')
            : collect();

        return view('actividades.show', compact('actividad', 'respuesta', 'criteriosRubrica', 'seleccionesMap'));
    }

    public function edit(Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);
        $preguntas        = $actividad->preguntas()->with('opciones')->get();
        $criteriosRubrica = $actividad->criteriosRubrica()->with('niveles')->get();
        return view('actividades.edit', compact('actividad', 'preguntas', 'criteriosRubrica'));
    }

    public function update(Request $request, Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);

        $validated = $request->validate([
            'titulo'           => 'required|string|max:255',
            'descripcion'      => 'nullable|string',
            'puntaje_maximo'   => 'required|integer|min:1|max:1000',
            'duracion_minutos' => 'nullable|integer|min:1',
            'es_obligatoria'   => 'boolean',
            'fecha_apertura'   => 'nullable|date',
            'fecha_cierre'     => 'nullable|date|after_or_equal:fecha_apertura',
        ]);

        $actividad->update([
            ...$validated,
            'es_obligatoria' => $request->boolean('es_obligatoria', true),
            'fecha_apertura'  => $request->filled('fecha_apertura') ? $request->fecha_apertura : null,
            'fecha_cierre'    => $request->filled('fecha_cierre')   ? $request->fecha_cierre   : null,
        ]);

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Actividad actualizada correctamente');
    }

    public function destroy(Actividad $actividad)
    {
        $curso = $actividad->leccion->modulo->curso;
        $this->authorize('update', $curso);
        $actividad->delete();

        return redirect()
            ->route('cursos.edit', $curso)
            ->with('success', 'Actividad eliminada');
    }
}