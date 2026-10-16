<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrarUltimoAcceso
{
    private const MINUTOS_ENTRE_ESCRITURAS = 10;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (!$user->ultimo_acceso || $user->ultimo_acceso->lt(now()->subMinutes(self::MINUTOS_ENTRE_ESCRITURAS)))) {
            // Directo a la tabla: sin eventos de Eloquent (no toca updated_at ni la auditoría).
            User::whereKey($user->id)->toBase()->update(['ultimo_acceso' => now()]);
        }

        return $next($request);
    }
}
