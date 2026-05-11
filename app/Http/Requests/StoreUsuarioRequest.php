<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->esAdmin();
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'empresa'  => 'nullable|string|max:255',
            'estado'   => 'required|in:activo,inactivo',
            'roles'    => 'required|array|min:1',
            'roles.*'  => 'exists:roles,id',
        ];
    }
}
