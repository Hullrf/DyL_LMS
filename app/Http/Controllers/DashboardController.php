<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\ProgresoLeccion;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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

    private function mesRaw(): string
    {
        return \DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at) as mes"
            : "DATE_FORMAT(created_at, '%Y-%m') as mes";
    }

    private function dashboardAdmin()
    {
        $stats = Cache::remember('dashboard.admin.stats', 300, function () {
            $inscripciones = Inscripcion::selectRaw($this->mesRaw() . ', COUNT(*) as total')
                ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
                ->groupBy('mes')
                ->orderBy('mes')
                ->pluck('total', 'mes');

            $meses = collect();
            for ($i = 5; $i >= 0; $i--) {
                $key = now()->subMonths($i)->format('Y-m');
                $meses[$key] = $inscripciones[$key] ?? 0;
            }

            return [
                'total_cursos'       => Curso::count(),
                'cursos_publicados'  => Curso::where('estado', 'publicado')->count(),
                'cursos_borrador'    => Curso::where('estado', 'borrador')->count(),
                'cursos_archivados'  => Curso::where('estado', 'archivado')->count(),
                'total_usuarios'     => User::count(),
                'total_instructores' => User::whereHas('roles', fn($q) => $q->where('nombre', 'Instructor'))->count(),
                'meses_labels'       => $meses->keys()
                    ->map(fn($m) => Carbon::parse($m . '-01')->locale('es')->isoFormat('MMM YY'))
                    ->values(),
                'meses_data'         => $meses->values(),
            ];
        });

        $cursos_recientes = Cache::remember('dashboard.admin.recientes', 180, fn() =>
            Curso::with('creador')->orderByDesc('created_at')->take(5)->get()
        );

        return view('dashboard.admin', compact('stats', 'cursos_recientes'));
    }

    private function dashboardInstructor(User $user)
    {
        $cursos = $user->cursosCreados()->with(['modulos.lecciones', 'inscripciones'])->get();

        $progresoPorCurso = $cursos->map(function ($curso) {
            $leccionIds     = $curso->lecciones->pluck('id');
            $totalLecciones = $leccionIds->count();

            if ($totalLecciones === 0 || $curso->inscripciones->isEmpty()) {
                return 0;
            }

            $promedio = $curso->inscripciones->map(function ($insc) use ($leccionIds, $totalLecciones) {
                $completadas = ProgresoLeccion::where('user_id', $insc->user_id)
                    ->whereIn('leccion_id', $leccionIds)
                    ->where('completado', true)
                    ->count();

                return $totalLecciones > 0 ? round(($completadas / $totalLecciones) * 100) : 0;
            })->avg();

            return round($promedio);
        })->values();

        $pendientes_calificar = RespuestaEstudiante::where('estado', 'sin_calificar')
            ->whereHas('actividad', function ($q) use ($user) {
                $q->whereIn('tipo', ['ensayo', 'tarea', 'practica'])
                  ->whereHas('leccion.modulo.curso', fn($q2) => $q2->where('created_by', $user->id));
            })->count();

        $stats = [
            'mis_cursos'            => $cursos->count(),
            'cursos_publicados'     => $cursos->where('estado', 'publicado')->count(),
            'estudiantes_inscritos' => $cursos->sum(fn($c) => $c->inscripciones->count()),
            'pendientes_calificar'  => $pendientes_calificar,
            'progreso_por_curso'    => $progresoPorCurso,
        ];

        return view('dashboard.instructor', compact('cursos', 'stats'));
    }

    private function dashboardEstudiante(User $user)
    {
        $cursos_inscritos = $user->cursos()->with(['modulos.lecciones'])->get();

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
