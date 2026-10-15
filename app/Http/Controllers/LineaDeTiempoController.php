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

        $lineaDeTiempo = $servicio->para(
            $user,
            $dias,
            $request->integer('curso') ?: null,
            $request->boolean('entregadas'),
            $request->filled('buscar') ? trim($request->query('buscar')) : null,
            $request->integer('ronda') ?: null,
        );

        $cursos = Inscripcion::with('curso:id,titulo')->where('user_id', $user->id)->get()
            ->pluck('curso')->filter()->sortBy('titulo');

        return view('linea-de-tiempo.index', compact('lineaDeTiempo', 'cursos', 'dias'));
    }
}
