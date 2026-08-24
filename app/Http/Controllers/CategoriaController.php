<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoriaController extends Controller
{
    /**
     * Crea una categoría nueva desde el selector de categoría del formulario de curso (AJAX).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
        ]);

        $nombre = trim($validated['nombre']);

        $existe = Categoria::whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($nombre)])->exists();
        if ($existe) {
            return response()->json(['message' => 'Ya existe una categoría con ese nombre.'], 422);
        }

        $slug = $slugBase = Str::slug($nombre);
        $sufijo = 2;
        while (Categoria::where('slug', $slug)->exists()) {
            $slug = "{$slugBase}-{$sufijo}";
            $sufijo++;
        }

        try {
            $categoria = Categoria::create([
                'nombre' => $nombre,
                'slug'   => $slug,
            ]);
        } catch (QueryException $e) {
            // Condición de carrera: otra petición creó la misma categoría entre el chequeo y el insert.
            return response()->json(['message' => 'Ya existe una categoría con ese nombre.'], 422);
        }

        return response()->json([
            'id'     => $categoria->id,
            'nombre' => $categoria->nombre,
            'color'  => $categoria->color,
        ], 201);
    }
}
