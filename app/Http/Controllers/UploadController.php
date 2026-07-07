<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UploadController extends Controller
{
    /**
     * Recibe una imagen del editor de lecciones (Quill) y la almacena.
     * Devuelve la URL pública para que Quill la inserte en el contenido.
     */
    public function editorImagen(Request $request)
    {
        $request->validate([
            'imagen' => 'required|image|max:4096',
        ]);

        $yearMonth = now()->format('Y-m');
        $path = $request->file('imagen')->store("editor/{$yearMonth}", 'public');

        return response()->json(['url' => asset('storage/' . $path)]);
    }
}
