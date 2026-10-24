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
            'color'  => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
