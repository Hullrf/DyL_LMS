<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificacionController extends Controller
{
    public function index()
    {
        $notificaciones = Auth::user()->notificaciones()
            ->latest()
            ->paginate(20);

        return view('notificaciones.index', compact('notificaciones'));
    }

    public function marcarLeida(Notificacion $notificacion)
    {
        if ($notificacion->user_id !== Auth::id()) abort(403);

        $notificacion->update(['leido' => true]);

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        $url = $notificacion->data['url'] ?? null;
        return $url ? redirect($url) : back();
    }

    public function marcarTodasLeidas()
    {
        Auth::user()->notificaciones()
            ->where('leido', false)
            ->update(['leido' => true]);

        return back()->with('success', 'Todas las notificaciones marcadas como leídas.');
    }
}
