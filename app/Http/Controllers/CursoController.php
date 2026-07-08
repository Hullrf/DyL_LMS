<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CursoController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $inscripciones = Inscripcion::with('curso.creador')
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->filter(fn($i) => $i->curso !== null);

        $inscritosIds = $inscripciones->pluck('curso_id')->toArray();

        $catalogo = Curso::with(['creador', 'categoria'])
            ->when(!$user->esAdmin(), fn($q) => $q->where('estado', 'publicado'))
            ->when($request->filled('categoria'), fn($q) => $q->where('categoria_id', $request->categoria))
            ->when($request->filled('buscar'), fn($q) => $q->where('titulo', 'like', "%{$request->buscar}%"))
            ->whereNotIn('id', $inscritosIds)
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        $categorias = Categoria::orderBy('nombre')->get();

        return view('cursos.index', compact('inscripciones', 'catalogo', 'categorias'));
    }

    public function create()
    {
        $this->authorize('create', Curso::class);
        $categorias = Categoria::orderBy('nombre')->get();
        return view('cursos.create', compact('categorias'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Curso::class);

        $validated = $request->validate([
            'titulo'         => 'required|string|max:255|unique:cursos',
            'descripcion'    => 'required|string|min:20',
            'duracion_horas' => 'required|integer|min:1|max:500',
            'imagen_portada' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'categoria_id'   => 'nullable|exists:categorias,id',
        ]);

        $validated['created_by'] = Auth::id();

        if ($request->hasFile('imagen_portada')) {
            $slug = Str::slug($validated['titulo']);
            $ext  = $request->file('imagen_portada')->getClientOriginalExtension();
            $validated['imagen_portada'] = $request->file('imagen_portada')
                ->storeAs("cursos/{$slug}", "portada.{$ext}", 'public');
        }

        $curso = Curso::create($validated);

        Cache::forget('dashboard.admin.stats');
        Cache::forget('dashboard.admin.recientes');

        return redirect()->route('cursos.edit', $curso)->with('success', 'Curso creado exitosamente');
    }

    public function show(Curso $curso)
    {
        $this->authorize('view', $curso);

        $modulos = $curso->modulos()->with(['lecciones.actividades'])->get();

        // Datos de progreso del usuario actual
        $inscripcion = Inscripcion::where('user_id', Auth::id())
            ->where('curso_id', $curso->id)
            ->first();

        $estaInscrito = $inscripcion !== null;

        $totalLecciones = $modulos->flatMap->lecciones->count();

        $completadasIds = collect();
        $porcentaje     = 0;

        if ($estaInscrito && $totalLecciones > 0) {
            $leccionesIds = $modulos->flatMap->lecciones->pluck('id');
            $completadasIds = Auth::user()->progresoLecciones()
                ->whereIn('leccion_id', $leccionesIds)
                ->where('completado', true)
                ->pluck('leccion_id');
            $porcentaje = (int) round(($completadasIds->count() / $totalLecciones) * 100);
        }

        return view('cursos.show', compact(
            'curso', 'modulos', 'inscripcion', 'estaInscrito',
            'completadasIds', 'porcentaje', 'totalLecciones'
        ));
    }

    public function inscribirse(Curso $curso)
    {
        $this->authorize('view', $curso);

        $yaInscrito = Inscripcion::where('user_id', Auth::id())
            ->where('curso_id', $curso->id)
            ->exists();

        if ($yaInscrito) {
            $primeraLeccion = $curso->modulos()
                ->orderBy('orden')
                ->with(['lecciones' => fn($q) => $q->orderBy('orden')])
                ->first()
                ?->lecciones
                ->first();

            if ($primeraLeccion) {
                return redirect()->route('lecciones.show', $primeraLeccion)
                    ->with('info', 'Ya estás inscrito en este curso. Continuando donde lo dejaste.');
            }
            return redirect()->route('cursos.show', $curso)
                ->with('info', 'Ya estás inscrito en este curso.');
        }

        Inscripcion::create([
            'user_id'     => Auth::id(),
            'curso_id'    => $curso->id,
            'fecha_inicio' => now()->toDateString(),
            'estado'       => 'en_progreso',
        ]);

        $primeraLeccion = $curso->modulos()
            ->orderBy('orden')
            ->with(['lecciones' => fn($q) => $q->orderBy('orden')])
            ->first()
            ?->lecciones
            ->first();

        if ($primeraLeccion) {
            return redirect()
                ->route('lecciones.show', $primeraLeccion)
                ->with('success', '¡Te has inscrito en el curso! Comenzando la primera lección.');
        }

        return redirect()
            ->route('cursos.show', $curso)
            ->with('success', '¡Te has inscrito en el curso!');
    }

    public function inscripcionMasiva(Curso $curso, Request $request)
    {
        $this->authorize('update', $curso);

        $inscritosIds = Inscripcion::where('curso_id', $curso->id)->pluck('user_id')->toArray();

        $usuarios = User::whereNotIn('id', $inscritosIds)
            ->when($request->filled('buscar'), fn($q) =>
                $q->where('name', 'like', "%{$request->buscar}%")
                  ->orWhere('email', 'like', "%{$request->buscar}%"))
            ->orderBy('name')
            ->paginate(20);

        return view('cursos.inscripcion-masiva', compact('curso', 'usuarios', 'inscritosIds'));
    }

    public function procesarInscripcionMasiva(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $request->validate([
            'usuarios' => 'required|array|min:1',
            'usuarios.*' => 'exists:users,id',
        ]);

        $inscritos = 0;
        foreach ($request->usuarios as $userId) {
            $created = Inscripcion::firstOrCreate(
                ['user_id' => $userId, 'curso_id' => $curso->id],
                ['fecha_inicio' => now()->toDateString(), 'estado' => 'en_progreso']
            );
            if ($created->wasRecentlyCreated) $inscritos++;
        }

        return redirect()
            ->route('cursos.inscripcion-masiva', $curso)
            ->with('success', "{$inscritos} estudiantes inscritos correctamente.");
    }

    public function edit(Curso $curso)
    {
        $this->authorize('update', $curso);
        $modulos = $curso->modulos()->with(['lecciones.actividades'])->get();
        $categorias = Categoria::orderBy('nombre')->get();
        return view('cursos.edit', compact('curso', 'modulos', 'categorias'));
    }

    public function update(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $validated = $request->validate([
            'titulo'         => 'required|string|max:255|unique:cursos,titulo,' . $curso->id,
            'descripcion'    => 'required|string|min:20',
            'duracion_horas' => 'required|integer|min:1|max:500',
            'estado'         => 'required|in:borrador,publicado,archivado',
            'imagen_portada' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'categoria_id'   => 'nullable|exists:categorias,id',
        ]);

        if ($request->hasFile('imagen_portada')) {
            if ($curso->imagen_portada) {
                Storage::disk('public')->delete($curso->imagen_portada);
            }
            $slug = Str::slug($validated['titulo']);
            $ext  = $request->file('imagen_portada')->getClientOriginalExtension();
            $validated['imagen_portada'] = $request->file('imagen_portada')
                ->storeAs("cursos/{$slug}", "portada.{$ext}", 'public');
        } else {
            unset($validated['imagen_portada']);
        }

        $curso->update($validated);

        Cache::forget('dashboard.admin.stats');
        Cache::forget('dashboard.admin.recientes');

        return redirect()->route('cursos.edit', $curso)->with('success', 'Curso actualizado correctamente');
    }

    public function destroy(Curso $curso)
    {
        $this->authorize('delete', $curso);
        $curso->delete();

        Cache::forget('dashboard.admin.stats');
        Cache::forget('dashboard.admin.recientes');

        return redirect()->route('cursos.index')->with('success', 'Curso eliminado correctamente');
    }
}
