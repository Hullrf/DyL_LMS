<?php

namespace App\Http\Controllers;

use App\Models\Foro;
use App\Models\ForoComentario;
use App\Models\Curso;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForoController extends Controller
{
    public function index(Curso $curso)
    {
        $foros = Foro::with(['creador', 'comentarios.usuario'])
            ->where('curso_id', $curso->id)
            ->latest()
            ->get();

        return view('foros.index', compact('curso', 'foros'));
    }

    public function show(Foro $foro)
    {
        $foro->load(['creador', 'comentarios.usuario', 'comentarios.respuestas.usuario', 'curso', 'leccion']);
        return view('foros.show', compact('foro'));
    }

    public function create(Curso $curso)
    {
        $this->authorize('update', $curso);
        $lecciones = $curso->modulos()->with('lecciones')->get()->flatMap->lecciones;
        return view('foros.create', compact('curso', 'lecciones'));
    }

    public function store(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);
        $validated = $request->validate([
            'titulo'      => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'leccion_id'  => 'nullable|exists:lecciones,id',
        ]);
        $foro = Foro::create($validated + ['curso_id' => $curso->id, 'created_by' => Auth::id()]);

        $inscritos = $curso->inscripciones()->pluck('user_id');
        foreach ($inscritos as $uid) {
            if ($uid != Auth::id()) {
                Notificacion::crear($uid, 'foro', 'Nuevo foro de discusión',
                    "Se abrió el foro «{$foro->titulo}» en el curso {$curso->titulo}.",
                    route('foros.show', $foro));
            }
        }

        return redirect()->route('foros.show', $foro)->with('success', 'Foro creado.');
    }

    public function comentar(Request $request, Foro $foro)
    {
        $validated = $request->validate([
            'contenido' => 'required|string|min:3',
            'padre_id'  => 'nullable|exists:foro_comentarios,id',
        ]);

        ForoComentario::create([
            'foro_id'  => $foro->id,
            'user_id'  => Auth::id(),
            'contenido'=> $validated['contenido'],
            'padre_id' => $validated['padre_id'] ?? null,
        ]);

        $creador = $foro->creador;
        if ($creador->id !== Auth::id()) {
            Notificacion::crear($creador->id, 'foro', 'Nuevo comentario en tu foro',
                Auth::user()->name . " comentó en «{$foro->titulo}».",
                route('foros.show', $foro));
        }

        return back()->with('success', 'Comentario publicado.');
    }
}
