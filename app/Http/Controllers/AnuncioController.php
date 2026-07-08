<?php

namespace App\Http\Controllers;

use App\Models\Anuncio;
use App\Models\Curso;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnuncioController extends Controller
{
    public function index(Curso $curso)
    {
        $anuncios = Anuncio::with('creador')
            ->where('curso_id', $curso->id)
            ->latest()
            ->paginate(10);

        return view('anuncios.index', compact('curso', 'anuncios'));
    }

    public function create(Curso $curso)
    {
        $this->authorize('update', $curso);
        return view('anuncios.create', compact('curso'));
    }

    public function store(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);
        $validated = $request->validate([
            'titulo'    => 'required|string|max:255',
            'contenido' => 'required|string|min:5',
        ]);

        $anuncio = Anuncio::create($validated + ['curso_id' => $curso->id, 'created_by' => Auth::id()]);

        $inscritos = $curso->inscripciones()->pluck('user_id');
        foreach ($inscritos as $uid) {
            if ($uid != Auth::id()) {
                Notificacion::crear($uid, 'anuncio', "Nuevo anuncio: {$anuncio->titulo}",
                    "{$curso->titulo}: {$anuncio->titulo}",
                    route('anuncios.index', $curso));
            }
        }

        return redirect()->route('anuncios.index', $curso)->with('success', 'Anuncio publicado.');
    }

    public function todos()
    {
        $cursosIds = Auth::user()->cursos()->pluck('cursos.id');
        if (Auth::user()->esAdmin() || Auth::user()->esInstructor()) {
            $cursosIds = Auth::user()->cursosCreados()->pluck('id')
                ->merge($cursosIds)->unique();
        }

        $anuncios = Anuncio::with(['creador', 'curso'])
            ->whereIn('curso_id', $cursosIds)
            ->latest()
            ->paginate(15);

        return view('anuncios.todos', compact('anuncios'));
    }
}
