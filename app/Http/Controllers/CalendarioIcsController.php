<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CalendarioService;
use App\Services\IcsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CalendarioIcsController extends Controller
{
    public function descargar(Request $request, CalendarioService $cal, IcsService $ics)
    {
        return $this->respuesta($request->user(), $cal, $ics)
            ->header('Content-Disposition', 'attachment; filename="calendario.ics"');
    }

    public function feed(string $token, CalendarioService $cal, IcsService $ics)
    {
        $user = User::where('calendario_token', $token)->firstOrFail();

        return $this->respuesta($user, $cal, $ics);
    }

    public function guardarToken(Request $request)
    {
        $request->user()->forceFill(['calendario_token' => Str::random(64)])->save();

        return back()->with('success', 'Enlace de suscripción generado. El anterior dejó de funcionar.');
    }

    public function revocarToken(Request $request)
    {
        $request->user()->forceFill(['calendario_token' => null])->save();

        return back()->with('success', 'Enlace de suscripción revocado.');
    }

    private function respuesta(User $user, CalendarioService $cal, IcsService $ics)
    {
        $eventos = $cal->eventos($user, now()->subDays(60)->startOfDay(), now()->addDays(365)->endOfDay());

        return response($ics->generar($eventos, 'D&L Quality Campus - ' . $user->name))
            ->header('Content-Type', 'text/calendar; charset=utf-8');
    }
}
