<?php

namespace App\Http\Controllers;

use App\Models\EventoPersonal;
use App\Models\Inscripcion;
use App\Services\CalendarioService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    public function index(Request $request, CalendarioService $calendario)
    {
        $user = $request->user();

        $m   = $request->query('mes');
        $mes = is_string($m) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)
            ? Carbon::createFromFormat('Y-m-d', $m . '-01')->startOfDay()
            : now()->startOfMonth();

        $c       = $request->query('curso');
        $cursoId = is_string($c) && ctype_digit($c) ? (int) $c : null;

        $desde = $mes->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $hasta = $mes->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $porDia = $calendario->eventos($user, $desde, $hasta, $cursoId)
            ->groupBy(fn($e) => $e->inicia->format('Y-m-d'));

        $semanas = collect($desde->toPeriod($hasta))->chunk(7);

        $cursos = Inscripcion::with('curso:id,titulo,estado')->where('user_id', $user->id)
            ->where('estado', '!=', 'abandonado')->get()
            ->pluck('curso')->filter(fn($c) => $c && $c->estado === 'publicado')->sortBy('titulo');

        return view('calendario.index', [
            'mes' => $mes, 'semanas' => $semanas, 'porDia' => $porDia, 'cursos' => $cursos,
            'cursoId' => $cursoId, 'anterior' => $mes->copy()->subMonth()->format('Y-m'),
            'siguiente' => $mes->copy()->addMonth()->format('Y-m'),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'titulo'      => 'required|string|max:255',
            'inicia_en'   => 'required|date',
            'termina_en'  => 'nullable|date|after_or_equal:inicia_en',
            'descripcion' => 'nullable|string|max:2000',
        ]);

        $evento = $request->user()->eventosPersonales()->create($datos);

        return redirect()->route('calendario.index', ['mes' => $evento->inicia_en->format('Y-m')])
            ->with('success', 'Evento agregado.');
    }

    public function destroy(Request $request, EventoPersonal $evento)
    {
        abort_unless($evento->user_id === $request->user()->id, 403);
        $mes = $evento->inicia_en->format('Y-m');
        $evento->delete();

        return redirect()->route('calendario.index', ['mes' => $mes])->with('success', 'Evento eliminado.');
    }
}
