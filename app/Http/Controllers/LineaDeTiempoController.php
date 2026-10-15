<?php

namespace App\Http\Controllers;

use App\Models\Inscripcion;
use App\Services\LineaDeTiempoService;
use Illuminate\Http\Request;

class LineaDeTiempoController extends Controller
{
    public function index(Request $request, LineaDeTiempoService $servicio)
    {
        $user = $request->user();
        $dias = in_array((int) $request->query('dias'), [7, 30, 180], true) ? (int) $request->query('dias') : 7;

        // Solo escalares: ?buscar[]=x etc. se ignoran en vez de provocar un 500.
        $entero = fn($k) => is_scalar($v = $request->query($k)) ? ((int) $v ?: null) : null;
        $buscar = is_string($q = $request->query('buscar')) && trim($q) !== '' ? trim($q) : null;
        $filtros = [
            'curso'      => $entero('curso'),
            'ronda'      => $entero('ronda'),
            'buscar'     => $buscar,
            'entregadas' => is_scalar($request->query('entregadas')) && $request->boolean('entregadas'),
        ];

        $lineaDeTiempo = $servicio->para($user, $dias, $filtros['curso'], $filtros['entregadas'], $buscar, $filtros['ronda']);

        $cursos = Inscripcion::with('curso:id,titulo,estado')->where('user_id', $user->id)
            ->where('estado', '!=', 'abandonado')->get()
            ->pluck('curso')->filter(fn($c) => $c && $c->estado === 'publicado')->sortBy('titulo');

        return view('linea-de-tiempo.index', compact('lineaDeTiempo', 'cursos', 'dias', 'filtros'));
    }
}
