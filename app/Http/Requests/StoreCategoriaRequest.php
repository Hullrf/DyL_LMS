<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->esAdmin();
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100|unique:categorias,nombre',
            'color'  => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
