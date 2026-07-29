<?php

namespace App\Http\Controllers;

use App\Models\Opcion;
use App\Models\Pregunta;
use Illuminate\Http\Request;

class OpcionController extends Controller
{


    public function store(Request $request, Pregunta $pregunta)
    {
        $this->authorize('update', $pregunta->actividad->leccion->modulo->curso);

        $validated = $request->validate([
            'texto'       => 'required|string|max:500',
            'es_correcta' => 'boolean',
            'explicacion' => 'nullable|string',
        ]);

        $orden = $pregunta->opciones()->max('orden') + 1;

        $pregunta->opciones()->create([
            ...$validated,
            'es_correcta' => $request->boolean('es_correcta'),
            'orden'        => $orden,
        ]);

        return redirect()
            ->route('actividades.edit', $pregunta->actividad)
            ->with('success', 'Opción agregada');
    }

    public function marcarCorrecta(Opcion $opcion)
    {
        $pregunta = $opcion->pregunta;
        $this->authorize('update', $pregunta->actividad->leccion->modulo->curso);

        if ($pregunta->seleccion_multiple) {
            $opcion->update(['es_correcta' => !$opcion->es_correcta]);
        } else {
            $pregunta->opciones()->update(['es_correcta' => false]);
            $opcion->update(['es_correcta' => true]);
        }

        return redirect()
            ->route('actividades.edit', $pregunta->actividad)
            ->with('success', 'Respuesta correcta actualizada');
    }

    public function destroy(Opcion $opcion)
    {
        $actividad = $opcion->pregunta->actividad;
        $this->authorize('update', $actividad->leccion->modulo->curso);
        $opcion->delete();

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Opción eliminada');
    }
}