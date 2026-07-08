<?php

namespace App\Http\Controllers;

use App\Models\Mensaje;
use App\Models\Curso;
use App\Models\User;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MensajeController extends Controller
{
    public function bandeja()
    {
        $user = Auth::user();
        $recibidos = $user->esAdmin() || $user->esInstructor()
            ? Mensaje::with(['remitente', 'curso'])->whereNull('padre_id')->latest()->paginate(20)
            : Mensaje::with(['remitente', 'curso'])
                ->where(function ($q) use ($user) {
                    $q->where('remitente_id', $user->id)->orWhereHas('curso', function ($q2) {
                        $q2->where('created_by', $user->id);
                    });
                })
                ->whereNull('padre_id')
                ->latest()
                ->paginate(20);

        $noLeidos = Auth::user()->notificaciones()->where('leido', false)->count();

        return view('mensajes.bandeja', compact('recibidos', 'noLeidos'));
    }

    public function conversacion(Mensaje $mensaje)
    {
        $user = Auth::user();
        if ($mensaje->remitente_id !== $user->id && $mensaje->curso->created_by !== $user->id && !$user->esAdmin()) {
            abort(403);
        }

        $mensaje->load(['remitente', 'curso', 'respuestas.remitente']);
        $mensaje->update(['leido' => true]);

        return view('mensajes.conversacion', compact('mensaje'));
    }

    public function create(Request $request)
    {
        $cursoId = $request->get('curso_id');
        $cursos = Curso::when(
            Auth::user()->esAdmin() || Auth::user()->esInstructor(),
            fn($q) => $q,
            fn($q) => $q->whereHas('inscripciones', fn($q2) => $q2->where('user_id', Auth::id()))
        )->orderBy('titulo')->get();

        $destinatarios = collect();
        if ($cursoId) {
            $destinatarios = User::whereHas('inscripciones', fn($q) => $q->where('curso_id', $cursoId))
                ->where('id', '!=', Auth::id())
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        return view('mensajes.create', compact('cursos', 'cursoId', 'destinatarios'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'curso_id'      => 'required|exists:cursos,id',
            'asunto'        => 'required|string|max:255',
            'mensaje'       => 'required|string|min:3',
            'padre_id'      => 'nullable|exists:mensajes,id',
            'destinatarios' => 'nullable|array',
            'destinatarios.*' => 'exists:users,id',
        ]);

        $mensaje = Mensaje::create([
            'curso_id'     => $validated['curso_id'],
            'remitente_id' => $user->id,
            'asunto'       => $validated['asunto'],
            'mensaje'      => $validated['mensaje'],
            'padre_id'     => $validated['padre_id'] ?? null,
        ]);

        $curso = $mensaje->curso;

        $destinatarioIds = $request->filled('destinatarios')
            ? $request->destinatarios
            : $curso->inscripciones()->where('user_id', '!=', $user->id)->pluck('user_id')->toArray();

        if (empty($destinatarioIds) && $user->esAdmin()) {
            $destinatarioIds = User::where('id', '!=', $user->id)->pluck('id')->toArray();
        }

        foreach ($destinatarioIds as $uid) {
            Notificacion::crear(
                $uid,
                'mensaje',
                "Nuevo mensaje: {$validated['asunto']}",
                "{$user->name} te envió un mensaje en el curso «{$curso->titulo}».",
                route('mensajes.conversacion', $mensaje)
            );
        }

        $total = count($destinatarioIds);
        return redirect()->route('mensajes.conversacion', $mensaje)
            ->with('success', "Mensaje enviado a {$total} destinatario(s).");
    }
}
