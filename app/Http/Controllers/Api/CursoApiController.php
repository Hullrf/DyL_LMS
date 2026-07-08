<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\Actividad;
use App\Models\Inscripcion;
use App\Models\ProgresoLeccion;
use App\Models\Leccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CursoApiController extends Controller
{
    public function index()
    {
        $cursos = Curso::with(['categoria', 'creador'])
            ->where('estado', 'publicado')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($c) => [
                'id'              => $c->id,
                'titulo'          => $c->titulo,
                'descripcion'     => $c->descripcion,
                'duracion_horas'  => $c->duracion_horas,
                'categoria'       => $c->categoria?->nombre,
                'imagen_portada'  => $c->imagen_portada ? asset('storage/' . $c->imagen_portada) : null,
                'instructor'      => $c->creador->name,
                'total_modulos'   => $c->modulos()->count(),
            ]);

        return response()->json($cursos);
    }

    public function show(Curso $curso)
    {
        $modulos = $curso->modulos()->with(['lecciones' => fn($q) => $q->orderBy('orden')])->get()
            ->map(fn($m) => [
                'id'        => $m->id,
                'titulo'    => $m->titulo,
                'lecciones' => $m->lecciones->map(fn($l) => [
                    'id'                 => $l->id,
                    'titulo'             => $l->titulo,
                    'duracion_minutos'   => $l->duracion_minutos,
                    'tipo'               => $l->tipo,
                    'tiene_video'        => (bool) $l->video_url,
                    'actividades_count'  => $l->actividades()->count(),
                ]),
            ]);

        return response()->json([
            'id'              => $curso->id,
            'titulo'          => $curso->titulo,
            'descripcion'     => $curso->descripcion,
            'duracion_horas'  => $curso->duracion_horas,
            'categoria'       => $curso->categoria?->nombre,
            'instructor'      => $curso->creador->name,
            'modulos'         => $modulos,
        ]);
    }

    public function misCursos()
    {
        $inscripciones = Inscripcion::with('curso')
            ->where('user_id', Auth::id())
            ->get()
            ->map(function ($i) {
                $curso = $i->curso;
                if (!$curso) return null;
                $total = $curso->lecciones()->count();
                $completadas = Auth::user()->progresoLecciones()
                    ->whereIn('leccion_id', $curso->lecciones()->pluck('lecciones.id'))
                    ->where('completado', true)
                    ->count();
                return [
                    'id'              => $curso->id,
                    'titulo'          => $curso->titulo,
                    'estado'          => $i->estado,
                    'progreso'        => $total > 0 ? (int) round(($completadas / $total) * 100) : 0,
                    'fecha_inicio'    => $i->fecha_inicio,
                    'fecha_fin'       => $i->fecha_fin,
                ];
            })->filter()->values();

        return response()->json($inscripciones);
    }

    public function misActividades()
    {
        $cursosIds = Inscripcion::where('user_id', Auth::id())->pluck('curso_id');

        $actividades = Actividad::with(['leccion.modulo.curso'])
            ->whereHas('leccion.modulo', fn($q) => $q->whereIn('curso_id', $cursosIds))
            ->whereNotNull('puntaje_maximo')
            ->whereDoesntHave('respuestas', fn($q) => $q->where('user_id', Auth::id()))
            ->orderBy('fecha_cierre')
            ->get()
            ->map(fn($a) => [
                'id'            => $a->id,
                'titulo'        => $a->titulo,
                'tipo'          => $a->tipo,
                'curso'         => $a->leccion->modulo->curso->titulo,
                'leccion'       => $a->leccion->titulo,
                'puntaje'       => $a->puntaje_maximo,
                'fecha_cierre'  => $a->fecha_cierre?->toDateString(),
                'url'           => route('actividades.show', $a),
            ]);

        return response()->json($actividades);
    }

    public function completarLeccion(Request $request, Leccion $leccion)
    {
        ProgresoLeccion::updateOrCreate(
            ['user_id' => Auth::id(), 'leccion_id' => $leccion->id],
            [
                'completado'              => true,
                'fecha_completado'        => now(),
                'tiempo_dedicado_minutos' => (int) ceil(($request->input('tiempo_segundos', 0) / 60)),
            ]
        );

        return response()->json(['ok' => true, 'message' => 'Lección completada.']);
    }
}
