<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use Illuminate\Http\Request;

class ModoEdicionController extends Controller
{
    public function alternar(Request $request, Curso $curso)
    {
        $this->authorize('update', $curso);

        $request->session()->put('modo_edicion', !$request->session()->get('modo_edicion', false));

        return redirect()->route('cursos.show', $curso);
    }
}
