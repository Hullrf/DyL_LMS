<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function index()
    {
        $usuarios = User::with('roles')
            ->when(request('buscar'), fn($q, $b) =>
                $q->where('name', 'like', "%{$b}%")->orWhere('email', 'like', "%{$b}%"))
            ->when(request('rol'), fn($q, $r) =>
                $q->whereHas('roles', fn($q2) => $q2->where('roles.id', $r)))
            ->when(request('estado'), fn($q, $e) => $q->where('estado', $e))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles = Rol::all();

        return view('admin.usuarios.index', compact('usuarios', 'roles'));
    }

    public function create()
    {
        $roles = Rol::all();

        return view('admin.usuarios.create', compact('roles'));
    }

    public function store(StoreUsuarioRequest $request)
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'empresa'  => $request->empresa,
            'estado'   => $request->estado,
        ]);

        $user->roles()->sync($request->roles);

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario {$user->name} creado correctamente.");
    }

    public function edit(User $usuario)
    {
        $roles        = Rol::all();
        $rolesActivos = $usuario->roles->pluck('id')->toArray();

        return view('admin.usuarios.edit', compact('usuario', 'roles', 'rolesActivos'));
    }

    public function update(UpdateUsuarioRequest $request, User $usuario)
    {
        $data = $request->only(['name', 'email', 'empresa', 'estado']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $usuario->update($data);
        $usuario->roles()->sync($request->roles);

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario {$usuario->name} actualizado correctamente.");
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $usuario->delete();

        return redirect()->route('admin.usuarios.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }
}
