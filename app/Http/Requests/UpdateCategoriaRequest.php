<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->esAdmin();
    }

    public function rules(): array
    {
        $categoriaId = $this->route('categoria')->id;

        return [
            'nombre' => "required|string|max:100|unique:categorias,nombre,{$categoriaId}",
            'color'  => 'nullable|string|max:20',
        ];
    }
}
