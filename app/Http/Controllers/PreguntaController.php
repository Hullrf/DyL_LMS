<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Pregunta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PreguntaController extends Controller
{
    public function store(Request $request, Actividad $actividad)
    {
        $this->authorize('update', $actividad->leccion->modulo->curso);

        $validated = $request->validate([
            'pregunta_texto'     => 'required|string',
            'tipo'               => 'required|in:opcion_multiple,verdadero_falso,respuesta_corta',
            'seleccion_multiple' => 'nullable|boolean',
            'correcta_vf'        => 'required_if:tipo,verdadero_falso|in:verdadero,falso',
            'imagen'             => 'nullable|image|max:4096',
        ]);

        $orden = $actividad->preguntas()->max('orden') + 1;

        $data = [
            'pregunta_texto'     => $validated['pregunta_texto'],
            'tipo'               => $validated['tipo'],
            'seleccion_multiple' => $validated['tipo'] === 'opcion_multiple' && !empty($validated['seleccion_multiple']),
            'puntaje'            => 1, // placeholder; se recalcula abajo
            'orden'              => $orden,
        ];

        if ($request->hasFile('imagen')) {
            $slug = Str::slug($actividad->leccion->modulo->curso->titulo);
            $data['imagen_path'] = $request->file('imagen')
                ->store("cursos/{$slug}/preguntas", 'public');
        }

        $pregunta = $actividad->preguntas()->create($data);

        if ($validated['tipo'] === 'verdadero_falso') {
            $correcta = $validated['correcta_vf'];
            $pregunta->opciones()->createMany([
                ['texto' => 'Verdadero', 'es_correcta' => $correcta === 'verdadero', 'orden' => 1],
                ['texto' => 'Falso',     'es_correcta' => $correcta === 'falso',     'orden' => 2],
            ]);
        }

        $actividad->redistribuirPuntajesPreguntas();

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Pregunta agregada');
    }

    public function update(Request $request, Pregunta $pregunta)
    {
        $this->authorize('update', $pregunta->actividad->leccion->modulo->curso);

        $request->validate([
            'pregunta_texto' => 'required|string',
            'imagen'         => 'nullable|image|max:4096',
        ]);

        $data = ['pregunta_texto' => $request->pregunta_texto];

        if ($request->hasFile('imagen')) {
            if ($pregunta->imagen_path) {
                Storage::disk('public')->delete($pregunta->imagen_path);
            }
            $data['imagen_path'] = $request->file('imagen')->store('preguntas', 'public');
        }

        $pregunta->update($data);

        if ($pregunta->tipo === 'verdadero_falso' && $request->filled('correcta_vf')) {
            $request->validate(['correcta_vf' => 'in:verdadero,falso']);
            foreach ($pregunta->opciones as $opcion) {
                $opcion->update([
                    'es_correcta' => strtolower($opcion->texto) === $request->correcta_vf,
                ]);
            }
        }

        return redirect()
            ->route('actividades.edit', $pregunta->actividad)
            ->with('success', 'Pregunta actualizada');
    }

    public function destroyImagen(Pregunta $pregunta)
    {
        $this->authorize('update', $pregunta->actividad->leccion->modulo->curso);

        if ($pregunta->imagen_path) {
            Storage::disk('public')->delete($pregunta->imagen_path);
            $pregunta->update(['imagen_path' => null]);
        }

        return redirect()
            ->route('actividades.edit', $pregunta->actividad)
            ->with('success', 'Imagen eliminada');
    }

    public function destroy(Pregunta $pregunta)
    {
        $actividad = $pregunta->actividad;
        $this->authorize('update', $actividad->leccion->modulo->curso);

        if ($pregunta->imagen_path) {
            Storage::disk('public')->delete($pregunta->imagen_path);
        }

        $pregunta->delete();

        $actividad->redistribuirPuntajesPreguntas();

        return redirect()
            ->route('actividades.edit', $actividad)
            ->with('success', 'Pregunta eliminada');
    }
}
