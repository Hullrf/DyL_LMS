<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->esAdmin()) {
            return $this->dashboardAdmin();
        } elseif ($user->esInstructor()) {
            return $this->dashboardInstructor($user);
        } else {
            return $this->dashboardEstudiante($user);
        }
    }

    private function dashboardAdmin()
    {
        $stats = [
            'total_cursos'       => Curso::count(),
            'cursos_publicados'  => Curso::where('estado', 'publicado')->count(),
            'total_usuarios'     => User::count(),
            'total_instructores' => User::whereHas('roles', fn($q) => $q->where('nombre', 'Instructor'))->count(),
        ];

        $cursos_recientes = Curso::with('creador')->orderBy('created_at', 'desc')->take(5)->get();

        return view('dashboard.admin', compact('stats', 'cursos_recientes'));
    }

    private function dashboardInstructor(User $user)
    {
        $cursos = $user->cursosCreados()->with('modulos')->get();

        $pendientes_calificar = RespuestaEstudiante::where('estado', 'sin_calificar')
            ->whereHas('actividad', function ($q) use ($user) {
                $q->whereIn('tipo', ['ensayo', 'tarea', 'practica'])
                  ->whereHas('leccion.modulo.curso', fn($q2) => $q2->where('created_by', $user->id));
            })->count();

        $stats = [
            'mis_cursos'             => $cursos->count(),
            'cursos_publicados'      => $cursos->where('estado', 'publicado')->count(),
            'estudiantes_inscritos'  => 0,
            'pendientes_calificar'   => $pendientes_calificar,
        ];

        return view('dashboard.instructor', compact('cursos', 'stats'));
    }

    private function dashboardEstudiante(User $user)
    {
        $cursos_inscritos = $user->cursos()->with(['modulos.lecciones'])->get();

        // Calcular progreso real
        $totalLecciones = $cursos_inscritos->sum(fn($c) => $c->modulos->sum(fn($m) => $m->lecciones->count()));

        $completadasCount = $user->progresoLecciones()->where('completado', true)->count();

        $progreso_general = $totalLecciones > 0
            ? (int) round(($completadasCount / $totalLecciones) * 100)
            : 0;

        $stats = [
            'cursos_activos'   => $cursos_inscritos->where('pivot.estado', 'en_progreso')->count(),
            'completados'      => $cursos_inscritos->where('pivot.estado', 'completado')->count(),
            'progreso_general' => $progreso_general,
        ];

        return view('dashboard.estudiante', compact('cursos_inscritos', 'stats'));
    }
}
