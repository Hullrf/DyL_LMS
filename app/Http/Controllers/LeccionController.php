<?php

namespace App\Http\Controllers;

use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\ProgresoLeccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeccionController extends Controller
{
    // ---------------------------------------------------------------
    // Estudiante: ver reproductor de lección
    // ---------------------------------------------------------------

    public function show(Leccion $leccion)
    {
        $curso = $leccion->modulo->curso;
        $this->authorize('view', $curso);

        // Sidebar: todos los módulos con sus lecciones
        $modulos = $curso->modulos()->with(['lecciones' => fn($q) => $q->orderBy('orden')])->get();

        // IDs de lecciones completadas por el usuario
        $todasLeccionesIds = $modulos->flatMap->lecciones->pluck('id');

        $completadasIds = Auth::user()->progresoLecciones()
            ->whereIn('leccion_id', $todasLeccionesIds)
            ->where('completado', true)
            ->pluck('leccion_id');

        $estaCompletada = $completadasIds->contains($leccion->id);

        $tiempoReal = Auth::user()->progresoLecciones()
            ->where('leccion_id', $leccion->id)
            ->where('completado', true)
            ->value('tiempo_dedicado_minutos');

        // Lección anterior y siguiente (orden lineal: módulo por módulo)
        $todasLecciones = $modulos->flatMap->lecciones;
        $idx            = $todasLecciones->search(fn($l) => $l->id === $leccion->id);
        $prevLeccion    = $idx > 0 ? $todasLecciones->values()[$idx - 1] : null;
        $nextLeccion    = $idx < $todasLecciones->count() - 1 ? $todasLecciones->values()[$idx + 1] : null;

        // Actividades de esta lección
        $actividades = $leccion->actividades()->get();

        // Respuestas del usuario en estas actividades
        $actividadesIds = $actividades->pluck('id');
        $respuestasIds  = Auth::user()->respuestas()
            ->whereIn('actividad_id', $actividadesIds)
            ->pluck('actividad_id');

        return view('lecciones.show', compact(
            'leccion', 'curso', 'modulos',
            'completadasIds', 'estaCompletada', 'tiempoReal',
            'prevLeccion', 'nextLeccion',
            'actividades', 'respuestasIds'
        ));
    }

    // ---------------------------------------------------------------
    // Estudiante: marcar lección como completada
    // ---------------------------------------------------------------

    public function completar(Leccion $leccion)
    {
        $curso = $leccion->modulo->curso;
        $this->authorize('view', $curso);

        ProgresoLeccion::updateOrCreate(
            ['user_id' => Auth::id(), 'leccion_id' => $leccion->id],
            [
                'completado'              => true,
                'fecha_completado'        => now(),
                'tiempo_dedicado_minutos' => (int) ceil(($request->input('tiempo_segundos', 0) / 60)),
            ]
        );

        // Verificar si el curso quedó 100% completado
        $totalLecciones    = $curso->lecciones()->count();
        $leccionsIds       = $curso->lecciones()->pluck('lecciones.id');
        $completadasCount  = Auth::user()->progresoLecciones()
            ->whereIn('leccion_id', $leccionsIds)
            ->where('completado', true)
            ->count();

        if ($totalLecciones > 0 && $completadasCount >= $totalLecciones) {
            Inscripcion::where('user_id', Auth::id())
                ->where('curso_id', $curso->id)
                ->update(['estado' => 'completado', 'fecha_fin' => now()->toDateString()]);
        }

        return redirect()
            ->route('lecciones.show', $leccion)
            ->with('success', '¡Lección marcada como completada!');
    }

    // ---------------------------------------------------------------
    // Instructor: crear / editar / eliminar
    // ---------------------------------------------------------------

    public function create(Modulo $modulo)
    {
        $this->authorize('update', $modulo->curso);
        return view('lecciones.create', compact('modulo'));
    }

    public function store(Request $request, Modulo $modulo)
    {
        $this->authorize('update', $modulo->curso);

        $validated = $request->validate([
            'titulo'                      => 'required|string|max:255',
            'contenido_html'              => 'nullable|string',
            'video_url'                   => 'nullable|url|max:2048',
            'duracion_minutos'            => 'required|integer|min:1',
            'tipo'                        => 'required|in:video,texto,mixto',
            'permitir_descarga_adjuntos'  => 'boolean',
        ]);

        $orden = $modulo->lecciones()->max('orden') + 1;
        $modulo->lecciones()->create([...$validated, 'orden' => $orden]);

        return redirect()
            ->route('cursos.edit', $modulo->curso)
            ->with('success', 'Lección creada correctamente');
    }

    public function edit(Leccion $leccion)
    {
        $this->authorize('update', $leccion->modulo->curso);
        $modulo = $leccion->modulo;
        return view('lecciones.edit', compact('leccion', 'modulo'));
    }

    public function update(Request $request, Leccion $leccion)
    {
        $this->authorize('update', $leccion->modulo->curso);

        $validated = $request->validate([
            'titulo'                      => 'required|string|max:255',
            'contenido_html'              => 'nullable|string',
            'video_url'                   => 'nullable|url|max:2048',
            'duracion_minutos'            => 'required|integer|min:1',
            'tipo'                        => 'required|in:video,texto,mixto',
            'permitir_descarga_adjuntos'  => 'boolean',
        ]);

        $leccion->update($validated);

        return redirect()
            ->route('cursos.edit', $leccion->modulo->curso)
            ->with('success', 'Lección actualizada correctamente');
    }

    public function destroy(Leccion $leccion)
    {
        $curso = $leccion->modulo->curso;
        $this->authorize('update', $curso);
        $leccion->delete();

        return redirect()
            ->route('cursos.edit', $curso)
            ->with('success', 'Lección eliminada correctamente');
    }
}
