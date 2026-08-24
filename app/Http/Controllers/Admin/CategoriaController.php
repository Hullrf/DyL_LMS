<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoriaRequest;
use App\Http\Requests\UpdateCategoriaRequest;
use App\Models\Categoria;
use Illuminate\Support\Str;

class CategoriaController extends Controller
{
    public function index()
    {
        $categorias = Categoria::withCount('cursos')
            ->when(request('buscar'), fn($q, $b) => $q->where('nombre', 'like', "%{$b}%"))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('admin.categorias.index', compact('categorias'));
    }

    public function create()
    {
        return view('admin.categorias.create');
    }

    public function store(StoreCategoriaRequest $request)
    {
        $categoria = Categoria::create([
            'nombre' => $request->nombre,
            'slug'   => $this->slugUnico($request->nombre),
            'color'  => $request->color ?: '#4F46E5',
        ]);

        return redirect()->route('admin.categorias.index')
            ->with('success', "Categoría \"{$categoria->nombre}\" creada correctamente.");
    }

    public function edit(Categoria $categoria)
    {
        return view('admin.categorias.edit', compact('categoria'));
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria)
    {
        $categoria->update([
            'nombre' => $request->nombre,
            'slug'   => $request->nombre === $categoria->nombre
                ? $categoria->slug
                : $this->slugUnico($request->nombre, $categoria->id),
            'color'  => $request->color ?: $categoria->color,
        ]);

        return redirect()->route('admin.categorias.index')
            ->with('success', "Categoría \"{$categoria->nombre}\" actualizada correctamente.");
    }

    public function destroy(Categoria $categoria)
    {
        // La FK categoria_id en cursos es nullOnDelete: los cursos que la usaban
        // quedan sin categoría, no se bloquea ni se cascada el borrado.
        $categoria->delete();

        return redirect()->route('admin.categorias.index')
            ->with('success', 'Categoría eliminada correctamente.');
    }

    /**
     * Genera un slug a partir del nombre, con sufijo numérico si colisiona con
     * uno existente (mismo criterio que CategoriaController::store, el
     * selector inline de categoría en el formulario de curso).
     */
    private function slugUnico(string $nombre, ?int $ignorarId = null): string
    {
        $slug = $slugBase = Str::slug($nombre);
        $sufijo = 2;

        while (Categoria::where('slug', $slug)->when($ignorarId, fn($q) => $q->where('id', '!=', $ignorarId))->exists()) {
            $slug = "{$slugBase}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
    }
}
