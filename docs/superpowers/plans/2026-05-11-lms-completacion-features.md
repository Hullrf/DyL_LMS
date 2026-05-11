# LMS DyL Quality — Completación de Features

> **Para agentes:** USA el skill `superpowers:subagent-driven-development` (recomendado) o `superpowers:executing-plans` para ejecutar este plan tarea por tarea. Los pasos usan sintaxis de checkbox (`- [ ]`) para seguimiento.

**Goal:** Completar el LMS DyL Quality con 9 features faltantes: git, gestión de usuarios admin, charts, Excel export, documentación, Redis, 2FA, auditoría y breadcrumbs.

**Architecture:** Laravel 12 + PHP 8.2 + MySQL + Blade + Alpine.js + Tailwind CSS. Proyecto en `C:\xampp\htdocs\LMS DyL\lms-dyl-quality\`. Todos los comandos se ejecutan desde esa carpeta raíz.

**Tech Stack:** Laravel 12, PHPUnit, mPDF, Tailwind CSS 4, Alpine.js, Chart.js, maatwebsite/excel, predis, pragmarx/google2fa, owen-it/laravel-auditing, diglactic/laravel-breadcrumbs

---

## FASE 0: Git Repository

### Task 0: Inicializar repositorio Git

**Files:** `.gitignore` (ya existe en Laravel)

- [ ] Inicializar el repositorio:

```bash
git init
git add .
git commit -m "chore: initial commit - LMS DyL Quality Consulting v1.0"
```

- [ ] Verificar:

```bash
git log --oneline
git status
```

Expected: 1 commit en el log, "nothing to commit" en status.

---

## FASE 1: Admin — Gestión de Usuarios (CRUD)

### Task 1.1: Form Requests

**Files:**
- Create: `app/Http/Requests/StoreUsuarioRequest.php`
- Create: `app/Http/Requests/UpdateUsuarioRequest.php`

- [ ] Crear `app/Http/Requests/StoreUsuarioRequest.php`:

```php
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
```

- [ ] Crear `app/Http/Requests/UpdateUsuarioRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->esAdmin();
    }

    public function rules(): array
    {
        $userId = $this->route('usuario')->id;

        return [
            'name'     => 'required|string|max:255',
            'email'    => "required|email|unique:users,email,{$userId}",
            'password' => 'nullable|string|min:8|confirmed',
            'empresa'  => 'nullable|string|max:255',
            'estado'   => 'required|in:activo,inactivo',
            'roles'    => 'required|array|min:1',
            'roles.*'  => 'exists:roles,id',
        ];
    }
}
```

### Task 1.2: UsuarioController

**Files:**
- Create: `app/Http/Controllers/Admin/UsuarioController.php`

- [ ] Crear el directorio y el controller:

```php
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
        $roles       = Rol::all();
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
```

### Task 1.3: Rutas admin

**Files:**
- Modify: `routes/web.php`

- [ ] Agregar grupo admin con resource de usuarios. Insertar antes de `require __DIR__.'/auth.php';`:

```php
// Admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class)
        ->except(['show']);
});
```

### Task 1.4: Vista index de usuarios

**Files:**
- Create: `resources/views/admin/usuarios/index.blade.php`

- [ ] Crear la vista:

```blade
@extends('layouts.app')
@section('title', 'Gestión de Usuarios — LMS DyL')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Gestión de Usuarios</h1>
        <a href="{{ route('admin.usuarios.create') }}" class="btn-primary">+ Nuevo Usuario</a>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap gap-3">
        <input type="text" name="buscar" value="{{ request('buscar') }}"
               placeholder="Nombre o email..." class="form-input flex-1 min-w-48">
        <select name="rol" class="form-input w-44">
            <option value="">Todos los roles</option>
            @foreach($roles as $rol)
                <option value="{{ $rol->id }}" @selected(request('rol') == $rol->id)>{{ $rol->nombre }}</option>
            @endforeach
        </select>
        <select name="estado" class="form-input w-36">
            <option value="">Todos los estados</option>
            <option value="activo"   @selected(request('estado') === 'activo')>Activo</option>
            <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivo</option>
        </select>
        <button type="submit" class="btn-primary">Filtrar</button>
        <a href="{{ route('admin.usuarios.index') }}" class="btn-outline">Limpiar</a>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="tbl w-full">
            <thead>
                <tr>
                    <th class="tbl-th">Nombre</th>
                    <th class="tbl-th">Email</th>
                    <th class="tbl-th">Empresa</th>
                    <th class="tbl-th">Rol</th>
                    <th class="tbl-th">Estado</th>
                    <th class="tbl-th">Registro</th>
                    <th class="tbl-th">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($usuarios as $usuario)
                <tr class="tbl-row">
                    <td class="tbl-td font-medium text-gray-900">{{ $usuario->name }}</td>
                    <td class="tbl-td text-gray-600">{{ $usuario->email }}</td>
                    <td class="tbl-td text-gray-600">{{ $usuario->empresa ?? '—' }}</td>
                    <td class="tbl-td">
                        @foreach($usuario->roles as $rol)
                            <span class="badge badge-blue">{{ $rol->nombre }}</span>
                        @endforeach
                    </td>
                    <td class="tbl-td">
                        @if($usuario->estado === 'activo')
                            <span class="badge badge-green">Activo</span>
                        @else
                            <span class="badge badge-red">Inactivo</span>
                        @endif
                    </td>
                    <td class="tbl-td text-gray-500 text-sm">{{ $usuario->created_at->format('d/m/Y') }}</td>
                    <td class="tbl-td">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="btn-outline btn-sm">Editar</a>
                            @if($usuario->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.usuarios.destroy', $usuario) }}"
                                  onsubmit="return confirm('¿Eliminar a {{ $usuario->name }}? Esta acción no se puede deshacer.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Eliminar</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="tbl-td text-center text-gray-400 py-10">No se encontraron usuarios.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $usuarios->links() }}</div>
</div>
@endsection
```

### Task 1.5: Vista create de usuarios

**Files:**
- Create: `resources/views/admin/usuarios/create.blade.php`

- [ ] Crear la vista:

```blade
@extends('layouts.app')
@section('title', 'Crear Usuario — LMS DyL')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.usuarios.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">← Usuarios</a>
        <span class="text-gray-400">/</span>
        <h1 class="text-2xl font-bold text-gray-900">Crear Usuario</h1>
    </div>

    <form method="POST" action="{{ route('admin.usuarios.store') }}"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 space-y-5">
        @csrf

        <div>
            <label class="form-label">Nombre completo</label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-input" required>
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-input" required>
            @error('email')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-input" required>
            @error('password')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Confirmar contraseña</label>
            <input type="password" name="password_confirmation" class="form-input" required>
        </div>
        <div>
            <label class="form-label">Empresa</label>
            <input type="text" name="empresa" value="{{ old('empresa') }}" class="form-input">
        </div>
        <div>
            <label class="form-label">Estado</label>
            <select name="estado" class="form-input">
                <option value="activo"   @selected(old('estado', 'activo') === 'activo')>Activo</option>
                <option value="inactivo" @selected(old('estado') === 'inactivo')>Inactivo</option>
            </select>
        </div>
        <div>
            <label class="form-label">Roles</label>
            <div class="space-y-2 mt-1">
            @foreach($roles as $rol)
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="roles[]" value="{{ $rol->id }}"
                           @checked(in_array($rol->id, old('roles', [])))
                           class="rounded text-blue-600">
                    <span class="text-sm text-gray-700 font-medium">{{ $rol->nombre }}</span>
                    @if($rol->descripcion)
                        <span class="text-xs text-gray-400">— {{ $rol->descripcion }}</span>
                    @endif
                </label>
            @endforeach
            </div>
            @error('roles')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Crear Usuario</button>
            <a href="{{ route('admin.usuarios.index') }}" class="btn-outline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
```

### Task 1.6: Vista edit de usuarios

**Files:**
- Create: `resources/views/admin/usuarios/edit.blade.php`

- [ ] Crear la vista:

```blade
@extends('layouts.app')
@section('title', 'Editar Usuario — ' . $usuario->name)
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.usuarios.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">← Usuarios</a>
        <span class="text-gray-400">/</span>
        <h1 class="text-2xl font-bold text-gray-900">Editar: {{ $usuario->name }}</h1>
    </div>

    <form method="POST" action="{{ route('admin.usuarios.update', $usuario) }}"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 space-y-5">
        @csrf @method('PUT')

        <div>
            <label class="form-label">Nombre completo</label>
            <input type="text" name="name" value="{{ old('name', $usuario->name) }}" class="form-input" required>
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email', $usuario->email) }}" class="form-input" required>
            @error('email')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">
                Nueva contraseña
                <span class="text-gray-400 font-normal">(dejar vacío para no cambiar)</span>
            </label>
            <input type="password" name="password" class="form-input">
            @error('password')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Confirmar nueva contraseña</label>
            <input type="password" name="password_confirmation" class="form-input">
        </div>
        <div>
            <label class="form-label">Empresa</label>
            <input type="text" name="empresa" value="{{ old('empresa', $usuario->empresa) }}" class="form-input">
        </div>
        <div>
            <label class="form-label">Estado</label>
            <select name="estado" class="form-input">
                <option value="activo"   @selected(old('estado', $usuario->estado) === 'activo')>Activo</option>
                <option value="inactivo" @selected(old('estado', $usuario->estado) === 'inactivo')>Inactivo</option>
            </select>
        </div>
        <div>
            <label class="form-label">Roles</label>
            <div class="space-y-2 mt-1">
            @foreach($roles as $rol)
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="roles[]" value="{{ $rol->id }}"
                           @checked(in_array($rol->id, old('roles', $rolesActivos)))
                           class="rounded text-blue-600">
                    <span class="text-sm text-gray-700 font-medium">{{ $rol->nombre }}</span>
                    @if($rol->descripcion)
                        <span class="text-xs text-gray-400">— {{ $rol->descripcion }}</span>
                    @endif
                </label>
            @endforeach
            </div>
            @error('roles')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary">Guardar Cambios</button>
            <a href="{{ route('admin.usuarios.index') }}" class="btn-outline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
```

### Task 1.7: Enlace "Usuarios" en navbar

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] Buscar la sección donde se muestran los enlaces de navegación para admin (buscar `esAdmin()` o la zona de links en el navbar) y agregar el enlace a Usuarios. Añadir junto a los otros enlaces admin:

```blade
@if(auth()->user()->esAdmin())
    <a href="{{ route('admin.usuarios.index') }}"
       class="{{ request()->routeIs('admin.usuarios.*') ? 'nav-link-active' : 'nav-link' }}">
        Usuarios
    </a>
@endif
```

### Task 1.8: Tests del controller

**Files:**
- Create: `tests/Feature/Admin/UsuarioControllerTest.php`

- [ ] Escribir el test:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $instructor;
    private Rol  $rolAdmin;
    private Rol  $rolEstudiante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rolAdmin      = Rol::create(['nombre' => 'Administrador']);
        $this->rolEstudiante = Rol::create(['nombre' => 'Estudiante']);
        $rolInstructor       = Rol::create(['nombre' => 'Instructor']);

        $this->admin = User::factory()->create(['estado' => 'activo']);
        $this->admin->roles()->attach($this->rolAdmin);

        $this->instructor = User::factory()->create(['estado' => 'activo']);
        $this->instructor->roles()->attach($rolInstructor);
    }

    public function test_admin_puede_ver_lista_usuarios(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.usuarios.index'));
        $response->assertOk()->assertViewIs('admin.usuarios.index');
    }

    public function test_instructor_no_puede_ver_lista_usuarios(): void
    {
        $response = $this->actingAs($this->instructor)->get(route('admin.usuarios.index'));
        $response->assertForbidden();
    }

    public function test_admin_puede_crear_usuario(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.usuarios.store'), [
            'name'                  => 'Nuevo Usuario',
            'email'                 => 'nuevo@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'empresa'               => 'Empresa Test',
            'estado'                => 'activo',
            'roles'                 => [$this->rolEstudiante->id],
        ]);

        $response->assertRedirect(route('admin.usuarios.index'));
        $this->assertDatabaseHas('users', ['email' => 'nuevo@test.com']);
    }

    public function test_admin_puede_editar_usuario(): void
    {
        $usuario = User::factory()->create(['estado' => 'activo']);
        $usuario->roles()->attach($this->rolEstudiante);

        $response = $this->actingAs($this->admin)->put(route('admin.usuarios.update', $usuario), [
            'name'    => 'Nombre Modificado',
            'email'   => $usuario->email,
            'empresa' => 'Nueva Empresa',
            'estado'  => 'inactivo',
            'roles'   => [$this->rolEstudiante->id],
        ]);

        $response->assertRedirect(route('admin.usuarios.index'));
        $this->assertDatabaseHas('users', [
            'id'     => $usuario->id,
            'name'   => 'Nombre Modificado',
            'estado' => 'inactivo',
        ]);
    }

    public function test_admin_puede_eliminar_usuario(): void
    {
        $usuario  = User::factory()->create();
        $response = $this->actingAs($this->admin)->delete(route('admin.usuarios.destroy', $usuario));

        $response->assertRedirect(route('admin.usuarios.index'));
        $this->assertSoftDeleted('users', ['id' => $usuario->id]);
    }

    public function test_admin_no_puede_eliminarse_a_si_mismo(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('admin.usuarios.destroy', $this->admin));

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'deleted_at' => null]);
    }
}
```

- [ ] Ejecutar los tests:

```bash
php artisan test tests/Feature/Admin/UsuarioControllerTest.php --verbose
```

Expected: 5 tests, 5 assertions — todos en verde.

- [ ] Commit:

```bash
git add app/Http/Controllers/Admin/UsuarioController.php \
        app/Http/Requests/StoreUsuarioRequest.php \
        app/Http/Requests/UpdateUsuarioRequest.php \
        resources/views/admin/usuarios/ \
        resources/views/layouts/app.blade.php \
        routes/web.php \
        tests/Feature/Admin/UsuarioControllerTest.php
git commit -m "feat: admin user management CRUD (index, create, edit, soft-delete)"
```

---

## FASE 2: Chart.js en Dashboards y Reportes

### Task 2.1: Instalar Chart.js

**Files:**
- Modify: `package.json`
- Modify: `resources/js/app.js`

- [ ] Instalar el paquete:

```bash
npm install chart.js
```

- [ ] Agregar al final de `resources/js/app.js`:

```js
import Chart from 'chart.js/auto';
window.Chart = Chart;
```

- [ ] Compilar y verificar:

```bash
npm run build
```

Expected: compilación exitosa sin errores.

### Task 2.2: Datos de chart en DashboardController (admin)

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`

- [ ] Reemplazar el método `dashboardAdmin()` completo por esta versión con datos de charts:

```php
private function dashboardAdmin()
{
    // Inscripciones últimos 6 meses
    $inscripciones = \App\Models\Inscripcion::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as total")
        ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
        ->groupBy('mes')
        ->orderBy('mes')
        ->pluck('total', 'mes');

    $meses = collect();
    for ($i = 5; $i >= 0; $i--) {
        $key = now()->subMonths($i)->format('Y-m');
        $meses[$key] = $inscripciones[$key] ?? 0;
    }

    $stats = [
        'total_cursos'        => Curso::count(),
        'cursos_publicados'   => Curso::where('estado', 'publicado')->count(),
        'cursos_borrador'     => Curso::where('estado', 'borrador')->count(),
        'cursos_archivados'   => Curso::where('estado', 'archivado')->count(),
        'total_usuarios'      => User::count(),
        'total_instructores'  => User::whereHas('roles', fn($q) => $q->where('nombre', 'Instructor'))->count(),
        'meses_labels'        => $meses->keys()
            ->map(fn($m) => \Carbon\Carbon::parse($m . '-01')->locale('es')->isoFormat('MMM YY'))
            ->values(),
        'meses_data'          => $meses->values(),
    ];

    $cursos_recientes = Curso::with('creador')->orderByDesc('created_at')->take(5)->get();

    return view('dashboard.admin', compact('stats', 'cursos_recientes'));
}
```

- [ ] Agregar `use Carbon\Carbon;` al bloque de imports al inicio del archivo (si no existe ya).

### Task 2.3: Charts en vista dashboard admin

**Files:**
- Modify: `resources/views/dashboard/admin.blade.php`

- [ ] Agregar antes del cierre de `@endsection` la sección de gráficos:

```blade
{{-- Gráficos --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Cursos por Estado</h3>
        <div style="height:200px; display:flex; align-items:center; justify-content:center;">
            <canvas id="chartCursosEstado"></canvas>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 lg:col-span-2">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Inscripciones — Últimos 6 meses</h3>
        <div style="height:200px;">
            <canvas id="chartInscripciones"></canvas>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartCursosEstado'), {
        type: 'doughnut',
        data: {
            labels: ['Borrador', 'Publicado', 'Archivado'],
            datasets: [{
                data: [{{ $stats['cursos_borrador'] }}, {{ $stats['cursos_publicados'] }}, {{ $stats['cursos_archivados'] }}],
                backgroundColor: ['#FCD34D', '#34D399', '#9CA3AF'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });

    new Chart(document.getElementById('chartInscripciones'), {
        type: 'bar',
        data: {
            labels: @json($stats['meses_labels']),
            datasets: [{
                label: 'Inscripciones',
                data: @json($stats['meses_data']),
                backgroundColor: '#3B82F6',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
@endpush
```

### Task 2.4: Datos de chart en DashboardController (instructor)

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`

- [ ] Reemplazar el método `dashboardInstructor()` completo:

```php
private function dashboardInstructor(User $user)
{
    $cursos = $user->cursosCreados()->with(['modulos.lecciones', 'inscripciones'])->get();

    $progresoPorCurso = $cursos->map(function ($curso) {
        $leccionIds     = $curso->lecciones->pluck('id');
        $totalLecciones = $leccionIds->count();

        if ($totalLecciones === 0 || $curso->inscripciones->isEmpty()) {
            return 0;
        }

        $promedio = $curso->inscripciones->map(function ($insc) use ($leccionIds, $totalLecciones) {
            $completadas = \App\Models\ProgresoLeccion::where('user_id', $insc->user_id)
                ->whereIn('leccion_id', $leccionIds)
                ->where('completado', true)
                ->count();

            return $totalLecciones > 0 ? round(($completadas / $totalLecciones) * 100) : 0;
        })->avg();

        return round($promedio);
    })->values();

    $pendientes = \App\Models\RespuestaEstudiante::whereIn(
        'actividad_id',
        \App\Models\Actividad::whereIn(
            'leccion_id',
            \App\Models\Leccion::whereIn(
                'modulo_id',
                \App\Models\Modulo::whereIn('curso_id', $cursos->pluck('id'))->pluck('id')
            )->pluck('id')
        )->pluck('id')
    )->where('estado', 'sin_calificar')->count();

    $stats = [
        'mis_cursos'            => $cursos->count(),
        'cursos_publicados'     => $cursos->where('estado', 'publicado')->count(),
        'estudiantes_inscritos' => $cursos->sum(fn($c) => $c->inscripciones->count()),
        'pendientes_calificar'  => $pendientes,
        'progreso_por_curso'    => $progresoPorCurso,
    ];

    return view('dashboard.instructor', compact('cursos', 'stats'));
}
```

### Task 2.5: Chart en vista dashboard instructor

**Files:**
- Modify: `resources/views/dashboard/instructor.blade.php`

- [ ] Agregar antes del cierre de `@endsection`:

```blade
@if($cursos->isNotEmpty())
<div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Progreso promedio por curso (%)</h3>
    <div style="height: 200px;">
        <canvas id="chartProgresoCursos"></canvas>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartProgresoCursos'), {
        type: 'bar',
        data: {
            labels: @json($cursos->pluck('titulo')),
            datasets: [{
                label: 'Progreso (%)',
                data: @json($stats['progreso_por_curso']),
                backgroundColor: '#6366F1',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
@endpush
@endif
```

### Task 2.6: Datos de chart en ReporteController

**Files:**
- Modify: `app/Http/Controllers/ReporteController.php`

- [ ] Agregar el método privado `buildChartData()` justo antes del cierre de la clase:

```php
private function buildChartData(): array
{
    $inscripciones = \App\Models\Inscripcion::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as total")
        ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
        ->groupBy('mes')
        ->orderBy('mes')
        ->pluck('total', 'mes');

    $meses = collect();
    for ($i = 5; $i >= 0; $i--) {
        $key = now()->subMonths($i)->format('Y-m');
        $meses[$key] = $inscripciones[$key] ?? 0;
    }

    $respEstados = \App\Models\RespuestaEstudiante::selectRaw('estado, COUNT(*) as total')
        ->groupBy('estado')
        ->pluck('total', 'estado');

    return [
        'meses_labels' => $meses->keys()
            ->map(fn($m) => \Carbon\Carbon::parse($m . '-01')->locale('es')->isoFormat('MMM YY'))
            ->values(),
        'meses_data'   => $meses->values(),
        'resp_estados' => [
            $respEstados['sin_calificar'] ?? 0,
            $respEstados['calificada']    ?? 0,
            $respEstados['en_revision']   ?? 0,
        ],
    ];
}
```

- [ ] Modificar el método `index()` existente (línea 38): cambiar el `return view(...)` para incluir `$chartData`:

```php
// Reemplazar la última línea del método index():
// ANTES:
//   return view('reportes.index', compact('kpis', 'cursos', 'usuarios'));
// DESPUÉS:
$chartData = $this->buildChartData();
return view('reportes.index', compact('kpis', 'cursos', 'usuarios', 'chartData'));
```

- [ ] Agregar `use Carbon\Carbon;` a los imports del archivo si no está ya.

### Task 2.7: Charts en vista reportes/index

**Files:**
- Modify: `resources/views/reportes/index.blade.php`

- [ ] Agregar antes del cierre de `@endsection`:

```blade
<div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Inscripciones por mes (últimos 6 meses)</h3>
        <div style="height: 220px;">
            <canvas id="chartInscMes"></canvas>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Estado de Respuestas</h3>
        <div style="height: 220px; display:flex; align-items:center; justify-content:center;">
            <canvas id="chartRespEstado"></canvas>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartInscMes'), {
        type: 'line',
        data: {
            labels: @json($chartData['meses_labels']),
            datasets: [{
                label: 'Inscripciones',
                data: @json($chartData['meses_data']),
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59,130,246,0.08)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#3B82F6',
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });

    new Chart(document.getElementById('chartRespEstado'), {
        type: 'doughnut',
        data: {
            labels: ['Sin calificar', 'Calificada', 'En revisión'],
            datasets: [{
                data: @json($chartData['resp_estados']),
                backgroundColor: ['#FCD34D', '#34D399', '#60A5FA'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });
});
</script>
@endpush
```

- [ ] Ejecutar la suite completa de tests para verificar que nada se rompió:

```bash
php artisan test
```

Expected: todos los tests existentes pasan.

- [ ] Commit:

```bash
git add resources/js/app.js package.json package-lock.json \
        app/Http/Controllers/DashboardController.php \
        app/Http/Controllers/ReporteController.php \
        resources/views/dashboard/admin.blade.php \
        resources/views/dashboard/instructor.blade.php \
        resources/views/reportes/index.blade.php
git commit -m "feat: Chart.js integration in admin/instructor dashboards and reports"
```

---

## FASE 3: Excel Export Real (.xlsx)

### Task 3.1: Instalar maatwebsite/excel

- [ ] Instalar el paquete:

```bash
composer require maatwebsite/excel
```

- [ ] Publicar configuración:

```bash
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

### Task 3.2: Export class para reporte de curso

**Files:**
- Create: `app/Exports/CursoReporteExport.php`

- [ ] Crear la clase:

```php
<?php

namespace App\Exports;

use App\Models\Curso;
use App\Models\ProgresoLeccion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CursoReporteExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(private Curso $curso) {}

    public function collection()
    {
        $leccionIds     = $this->curso->lecciones->pluck('id');
        $totalLecciones = $leccionIds->count();

        return $this->curso->inscripciones()->with('usuario')->get()
            ->map(function ($insc) use ($leccionIds, $totalLecciones) {
                $completadas = ProgresoLeccion::where('user_id', $insc->user_id)
                    ->whereIn('leccion_id', $leccionIds)
                    ->where('completado', true)
                    ->count();

                $progreso = $totalLecciones > 0
                    ? round(($completadas / $totalLecciones) * 100) . '%'
                    : '0%';

                return [
                    $insc->usuario->name,
                    $insc->usuario->email,
                    $insc->usuario->empresa ?? '—',
                    $insc->fecha_inicio,
                    ucfirst($insc->estado),
                    $progreso,
                    "{$completadas} / {$totalLecciones}",
                ];
            });
    }

    public function headings(): array
    {
        return ['Estudiante', 'Email', 'Empresa', 'Fecha Inscripción', 'Estado', 'Progreso', 'Lecciones Completadas'];
    }

    public function title(): string
    {
        return 'Reporte Curso';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E3A5F']],
            ],
        ];
    }
}
```

### Task 3.3: Export class para lista de usuarios

**Files:**
- Create: `app/Exports/UsuariosReporteExport.php`

- [ ] Crear la clase:

```php
<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class UsuariosReporteExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    public function collection()
    {
        return User::with('roles')->get()->map(fn($u) => [
            $u->name,
            $u->email,
            $u->empresa ?? '—',
            $u->roles->pluck('nombre')->join(', '),
            ucfirst($u->estado),
            $u->created_at->format('d/m/Y'),
        ]);
    }

    public function headings(): array
    {
        return ['Nombre', 'Email', 'Empresa', 'Roles', 'Estado', 'Fecha Registro'];
    }

    public function title(): string
    {
        return 'Usuarios';
    }
}
```

### Task 3.4: Métodos de exportación en ReporteController

**Files:**
- Modify: `app/Http/Controllers/ReporteController.php`

- [ ] Agregar imports al bloque `use` del archivo:

```php
use App\Exports\CursoReporteExport;
use App\Exports\UsuariosReporteExport;
use Maatwebsite\Excel\Facades\Excel;
```

- [ ] Agregar dos métodos al controller, antes del cierre de la clase:

```php
public function exportarExcelCurso(Curso $curso)
{
    $user = Auth::user();

    if (!$user->esAdmin() && $curso->created_by !== $user->id) {
        abort(403);
    }

    $nombre = 'reporte-' . \Str::slug($curso->titulo) . '-' . now()->format('Y-m-d') . '.xlsx';

    return Excel::download(new CursoReporteExport($curso), $nombre);
}

public function exportarExcelUsuarios()
{
    // Solo admin llega aquí (protegido por middleware 'admin' en ruta)
    return Excel::download(new UsuariosReporteExport(), 'usuarios-' . now()->format('Y-m-d') . '.xlsx');
}
```

### Task 3.5: Rutas de exportación Excel

**Files:**
- Modify: `routes/web.php`

- [ ] Agregar dentro del grupo `auth` (junto a las rutas de reportes existentes):

```php
Route::middleware('instructor')->group(function () {
    Route::get('/reportes/cursos/{curso}/excel',
        [\App\Http\Controllers\ReporteController::class, 'exportarExcelCurso'])
        ->name('reportes.excel.curso');
});

Route::middleware('admin')->group(function () {
    Route::get('/reportes/usuarios/excel',
        [\App\Http\Controllers\ReporteController::class, 'exportarExcelUsuarios'])
        ->name('reportes.excel.usuarios');
});
```

### Task 3.6: Botones Excel en vistas de reportes

**Files:**
- Modify: `resources/views/reportes/curso.blade.php`

- [ ] Agregar botón "Excel" junto al botón CSV existente (buscar el botón de CSV y añadir a su lado):

```blade
<a href="{{ route('reportes.excel.curso', $reporte['curso']) }}"
   class="btn-outline btn-sm flex items-center gap-1.5">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
    </svg>
    Excel
</a>
```

- [ ] Ejecutar tests:

```bash
php artisan test
```

Expected: todos los tests pasan.

- [ ] Commit:

```bash
git add app/Exports/ app/Http/Controllers/ReporteController.php \
        routes/web.php resources/views/reportes/curso.blade.php \
        config/excel.php
git commit -m "feat: Excel (.xlsx) export for course reports and user list"
```

---

## FASE 4: DEVELOPMENT.md

### Task 4.1: Crear DEVELOPMENT.md

**Files:**
- Create: `DEVELOPMENT.md` (en la raíz del proyecto `lms-dyl-quality/`)

- [ ] Crear el archivo con el siguiente contenido (adaptar si algo cambia durante la implementación):

El archivo debe cubrir estas secciones con contenido real:

**Sección 1 — Prerequisitos:**
- PHP 8.2+ con extensiones: gd, mbstring, pdo_mysql, zip, redis (o predis)
- MySQL 8.0+, Node.js 18+, Composer 2.x, XAMPP

**Sección 2 — Setup Local:**
```
git clone <repo> lms-dyl-quality && cd lms-dyl-quality
composer install
cp .env.example .env && php artisan key:generate
# Editar .env: DB_DATABASE=dyl_lms, DB_USERNAME=root, DB_PASSWORD=
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```
Tabla de usuarios de prueba: admin/instructor/student @dyl-quality.test / password123

**Sección 3 — Arquitectura:**
- Patrones: MVC, Service Layer, Policy Pattern, Middleware de roles
- Tabla de modelos: User, Rol, Curso, Modulo, Leccion, Actividad, Pregunta, Opcion, Inscripcion, ProgresoLeccion, RespuestaEstudiante, Certificado
- Estructura de controllers: `Admin/UsuarioController`, `Admin/AuditoriaController`, los controllers de dominio, `Auth/TwoFactorController`
- Servicios: CalificacionService, CertificadoService, ReporteService

**Sección 4 — Roles y flujo:**
- Diagrama textual: Visitante → Login → Dashboard (Admin / Instructor / Estudiante)
- Middlewares: `admin` (IsAdmin), `instructor` (IsInstructor), `2fa` (TwoFactorMiddleware)

**Sección 5 — Agregar una nueva feature (checklist de 8 pasos):**
migración → modelo → controller → rutas → vistas → tests → `php artisan test` → `npm run build`

**Sección 6 — Comandos frecuentes:**
`php artisan serve`, `npm run dev`, `php artisan migrate`, `migrate:fresh --seed`, `php artisan test`, `cache:clear`, `storage:link`

**Sección 7 — Troubleshooting:**
- "Class not found": `composer dump-autoload`
- Assets no actualizan: `npm run build`
- PDF no genera: verificar extensión `gd` en php.ini de XAMPP
- Error CSRF: verificar `@csrf` en el form
- Redis no conecta: verificar que el servicio Redis esté activo en Windows

- [ ] Commit:

```bash
git add DEVELOPMENT.md
git commit -m "docs: DEVELOPMENT.md with setup, architecture, commands and troubleshooting"
```

---

## FASE 5: Redis Caching

### Task 5.1: Instalar Predis

**PREREQUISITO:** Redis debe estar instalado en el sistema. En Windows: descargar e instalar desde https://github.com/microsoftarchive/redis/releases (archivo `Redis-x64-3.0.504.msi`). Arrancar el servicio `Redis` antes de continuar.

- [ ] Instalar el cliente PHP:

```bash
composer require predis/predis
```

- [ ] Actualizar `.env` con la configuración de Redis:

```
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

- [ ] Actualizar `.env.example` con los mismos valores (sin valores sensibles).

- [ ] Verificar que Redis responde:

```bash
php artisan tinker
# En tinker:
Cache::put('test_redis', 'ok', 60);
Cache::get('test_redis');
# Debe retornar "ok"
exit
```

Expected: retorna `"ok"`.

### Task 5.2: Cachear stats del dashboard admin

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`

- [ ] Envolver los datos calculados en `dashboardAdmin()` con `Cache::remember`. Agregar `use Illuminate\Support\Facades\Cache;` al bloque de imports. Luego reemplazar el método:

```php
private function dashboardAdmin()
{
    $stats = Cache::remember('dashboard.admin.stats', 300, function () {
        $inscripciones = \App\Models\Inscripcion::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as total")
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('mes')->orderBy('mes')->pluck('total', 'mes');

        $meses = collect();
        for ($i = 5; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $meses[$key] = $inscripciones[$key] ?? 0;
        }

        return [
            'total_cursos'       => Curso::count(),
            'cursos_publicados'  => Curso::where('estado', 'publicado')->count(),
            'cursos_borrador'    => Curso::where('estado', 'borrador')->count(),
            'cursos_archivados'  => Curso::where('estado', 'archivado')->count(),
            'total_usuarios'     => User::count(),
            'total_instructores' => User::whereHas('roles', fn($q) => $q->where('nombre', 'Instructor'))->count(),
            'meses_labels'       => $meses->keys()
                ->map(fn($m) => \Carbon\Carbon::parse($m . '-01')->locale('es')->isoFormat('MMM YY'))
                ->values(),
            'meses_data'         => $meses->values(),
        ];
    });

    $cursos_recientes = Cache::remember('dashboard.admin.recientes', 180, fn() =>
        Curso::with('creador')->orderByDesc('created_at')->take(5)->get()
    );

    return view('dashboard.admin', compact('stats', 'cursos_recientes'));
}
```

### Task 5.3: Invalidar cache al modificar cursos

**Files:**
- Modify: `app/Http/Controllers/CursoController.php`

- [ ] Agregar `use Illuminate\Support\Facades\Cache;` a los imports. Luego añadir al final de los métodos `store()`, `update()` y `destroy()` (antes del `return redirect()`):

```php
Cache::forget('dashboard.admin.stats');
Cache::forget('dashboard.admin.recientes');
```

- [ ] Ejecutar tests (en el entorno de test se usa driver `array`, no Redis, por lo que los tests no requieren Redis):

```bash
php artisan test
```

Expected: todos los tests pasan.

- [ ] Commit:

```bash
git add composer.json composer.lock \
        app/Http/Controllers/DashboardController.php \
        app/Http/Controllers/CursoController.php \
        .env.example
git commit -m "feat: Redis caching for admin dashboard stats (TTL 5 min)"
```

---

## FASE 6: 2FA (Two-Factor Authentication)

### Task 6.1: Instalar paquetes de 2FA

- [ ] Instalar las dependencias:

```bash
composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
```

- [ ] Publicar la configuración:

```bash
php artisan vendor:publish --provider="PragmaRX\Google2FALaravel\ServiceProvider"
```

### Task 6.2: Migración para columnas 2FA

- [ ] Crear la migración:

```bash
php artisan make:migration add_two_factor_to_users_table
```

- [ ] Editar el archivo de migración generado en `database/migrations/`:

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('two_factor_secret')->nullable()->after('password');
        $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['two_factor_secret', 'two_factor_enabled']);
    });
}
```

- [ ] Ejecutar:

```bash
php artisan migrate
```

### Task 6.3: Actualizar modelo User

**Files:**
- Modify: `app/Models/User.php`

- [ ] Agregar los nuevos campos a `$fillable` y `$hidden`, y el cast boolean:

```php
protected $fillable = [
    'name', 'email', 'password', 'empresa', 'estado',
    'two_factor_secret', 'two_factor_enabled',
];

protected $hidden = [
    'password', 'remember_token', 'two_factor_secret',
];

protected $casts = [
    'email_verified_at'  => 'datetime',
    'two_factor_enabled' => 'boolean',
];
```

### Task 6.4: TwoFactorController

**Files:**
- Create: `app/Http/Controllers/Auth/TwoFactorController.php`

- [ ] Crear el controller:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function setup()
    {
        $user      = auth()->user();
        $google2fa = app('pragmarx.google2fa');

        if (!$user->two_factor_secret) {
            $user->update(['two_factor_secret' => $google2fa->generateSecretKey()]);
        }

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret
        );

        $writer = new \BaconQrCode\Writer(
            new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            )
        );
        $qrSvg = base64_encode($writer->writeString($qrCodeUrl));

        return view('auth.two-factor.setup', compact('qrSvg', 'user'));
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => 'required|string|digits:6']);

        $user      = auth()->user();
        $google2fa = app('pragmarx.google2fa');

        if (!$google2fa->verifyKey($user->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => 'El código es incorrecto.']);
        }

        $user->update(['two_factor_enabled' => true]);

        return redirect()->route('profile.edit')
            ->with('success', 'Autenticación de dos factores activada correctamente.');
    }

    public function disable()
    {
        auth()->user()->update([
            'two_factor_enabled' => false,
            'two_factor_secret'  => null,
        ]);

        return redirect()->route('profile.edit')
            ->with('success', '2FA desactivado.');
    }

    public function verify()
    {
        return view('auth.two-factor.verify');
    }

    public function check(Request $request)
    {
        $request->validate(['code' => 'required|string|digits:6']);

        $user      = auth()->user();
        $google2fa = app('pragmarx.google2fa');

        if (!$google2fa->verifyKey($user->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => 'Código incorrecto. Intenta de nuevo.']);
        }

        session(['2fa_verified' => true]);

        return redirect()->intended(route('dashboard'));
    }
}
```

### Task 6.5: Middleware TwoFactor

**Files:**
- Create: `app/Http/Middleware/TwoFactorMiddleware.php`

- [ ] Crear el middleware:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TwoFactorMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if ($user && $user->two_factor_enabled && !session('2fa_verified')) {
            return redirect()->route('2fa.verify');
        }

        return $next($request);
    }
}
```

- [ ] Registrar el alias en `bootstrap/app.php`. El archivo actual tiene:

```php
$middleware->alias([
    'admin'      => \App\Http\Middleware\IsAdmin::class,
    'instructor' => \App\Http\Middleware\IsInstructor::class,
]);
```

Cambiarlo a:

```php
$middleware->alias([
    'admin'      => \App\Http\Middleware\IsAdmin::class,
    'instructor' => \App\Http\Middleware\IsInstructor::class,
    '2fa'        => \App\Http\Middleware\TwoFactorMiddleware::class,
]);
```

### Task 6.6: Rutas 2FA

**Files:**
- Modify: `routes/web.php`

- [ ] Agregar las rutas 2FA dentro del grupo `auth` (antes de las rutas protegidas):

```php
// 2FA (verificación, sin requerir 2fa ya verificado)
Route::middleware('auth')->group(function () {
    Route::get('/2fa/setup',    [\App\Http\Controllers\Auth\TwoFactorController::class, 'setup'])->name('2fa.setup');
    Route::post('/2fa/enable',  [\App\Http\Controllers\Auth\TwoFactorController::class, 'enable'])->name('2fa.enable');
    Route::post('/2fa/disable', [\App\Http\Controllers\Auth\TwoFactorController::class, 'disable'])->name('2fa.disable');
    Route::get('/2fa/verify',   [\App\Http\Controllers\Auth\TwoFactorController::class, 'verify'])->name('2fa.verify');
    Route::post('/2fa/check',   [\App\Http\Controllers\Auth\TwoFactorController::class, 'check'])->name('2fa.check');
});
```

- [ ] Agregar el middleware `2fa` al grupo principal de rutas protegidas. Localizar la línea:

```php
Route::middleware('auth')->group(function () {
```

Y cambiarla a (para el grupo principal, NO el grupo de rutas 2FA recién creado):

```php
Route::middleware(['auth', '2fa'])->group(function () {
```

**Nota:** Las rutas de 2FA (`setup`, `verify`, `check`) deben estar en un grupo `middleware('auth')` SIN el middleware `2fa`, para evitar redirección circular.

### Task 6.7: Vistas 2FA

**Files:**
- Create: `resources/views/auth/two-factor/setup.blade.php`
- Create: `resources/views/auth/two-factor/verify.blade.php`

- [ ] Crear vista setup:

```blade
@extends('layouts.app')
@section('title', 'Configurar 2FA — LMS DyL')
@section('content')
<div class="max-w-md mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <h1 class="text-xl font-bold text-gray-900 mb-2">Autenticación de dos factores</h1>
        <p class="text-gray-500 text-sm mb-6">
            Escanea este código QR con Google Authenticator, Authy u otra app TOTP compatible.
        </p>

        <div class="flex justify-center mb-6">
            <img src="data:image/svg+xml;base64,{{ $qrSvg }}"
                 alt="QR Code 2FA"
                 class="w-48 h-48 border border-gray-200 rounded-xl p-2 bg-white">
        </div>

        <p class="text-xs text-center text-gray-500 mb-6">
            ¿No puedes escanear? Usa la clave manual:<br>
            <code class="bg-gray-100 px-2 py-1 rounded font-mono text-sm">{{ $user->two_factor_secret }}</code>
        </p>

        <form method="POST" action="{{ route('2fa.enable') }}">
            @csrf
            <label class="form-label">Código de verificación (6 dígitos)</label>
            <input type="text" name="code" class="form-input text-center tracking-widest text-lg"
                   maxlength="6" inputmode="numeric" autocomplete="one-time-code" required autofocus>
            @error('code')<p class="form-error text-center mt-1">{{ $message }}</p>@enderror
            <button type="submit" class="btn-primary w-full mt-4">Activar 2FA</button>
        </form>
    </div>
</div>
@endsection
```

- [ ] Crear vista verify:

```blade
@extends('layouts.guest')
@section('title', 'Verificación 2FA — LMS DyL')
@section('content')
<div class="max-w-sm mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <div class="flex justify-center mb-4">
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
        </div>
        <h1 class="text-xl font-bold text-gray-900 text-center mb-2">Verificación de seguridad</h1>
        <p class="text-gray-500 text-sm text-center mb-6">
            Ingresa el código de 6 dígitos de tu app de autenticación.
        </p>
        <form method="POST" action="{{ route('2fa.check') }}">
            @csrf
            <input type="text" name="code"
                   class="form-input text-center text-2xl tracking-widest w-full"
                   maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                   required autofocus placeholder="000000">
            @error('code')<p class="form-error text-center mt-2">{{ $message }}</p>@enderror
            <button type="submit" class="btn-primary w-full mt-4">Verificar</button>
        </form>
    </div>
</div>
@endsection
```

- [ ] Agregar sección 2FA en `resources/views/profile/edit.blade.php`. Añadir antes del cierre del div principal:

```blade
{{-- 2FA --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-1">Autenticación de dos factores</h2>
    @if(auth()->user()->two_factor_enabled)
        <p class="text-green-600 text-sm mb-4 flex items-center gap-1">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            2FA activo — tu cuenta tiene una capa extra de seguridad.
        </p>
        <form method="POST" action="{{ route('2fa.disable') }}"
              onsubmit="return confirm('¿Desactivar la autenticación de dos factores?')">
            @csrf
            <button type="submit" class="btn-danger">Desactivar 2FA</button>
        </form>
    @else
        <p class="text-gray-500 text-sm mb-4">Añade seguridad extra a tu cuenta con una app de autenticación.</p>
        <a href="{{ route('2fa.setup') }}" class="btn-primary">Configurar 2FA</a>
    @endif
</div>
```

- [ ] Ejecutar tests:

```bash
php artisan test
```

Expected: todos los tests pasan.

- [ ] Commit:

```bash
git add app/Http/Controllers/Auth/TwoFactorController.php \
        app/Http/Middleware/TwoFactorMiddleware.php \
        app/Models/User.php \
        bootstrap/app.php \
        routes/web.php \
        database/migrations/*two_factor* \
        resources/views/auth/two-factor/ \
        resources/views/profile/edit.blade.php
git commit -m "feat: TOTP two-factor authentication via Google Authenticator"
```

---

## FASE 7: Auditoría de Acciones

### Task 7.1: Instalar laravel-auditing

- [ ] Instalar el paquete:

```bash
composer require owen-it/laravel-auditing
```

- [ ] Publicar configuración y migración:

```bash
php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider" --tag="config"
php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider" --tag="migrations"
php artisan migrate
```

### Task 7.2: Agregar trait Auditable a modelos clave

**Files:**
- Modify: `app/Models/Curso.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Actividad.php`
- Modify: `app/Models/RespuestaEstudiante.php`
- Modify: `app/Models/Inscripcion.php`

- [ ] Para **cada uno** de los 5 modelos, agregar el interface y el trait. Patrón (ejemplo con Curso.php):

```php
// Agregar estos dos use al bloque de imports:
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

// Cambiar la declaración de clase (agregar implements Auditable):
class Curso extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;  // agregar AuditableTrait a los traits existentes

    protected $auditExclude = ['updated_at'];

    // ... resto del modelo sin cambios
}
```

- [ ] Para `User.php` usar además `$auditThreshold` para limitar registros:

```php
class User extends Authenticatable implements Auditable
{
    use Notifiable, SoftDeletes, AuditableTrait;

    protected $auditExclude   = ['updated_at', 'remember_token', 'two_factor_secret'];
    protected $auditThreshold = 100;

    // ... resto sin cambios
}
```

- [ ] Para `RespuestaEstudiante.php`, `Actividad.php` e `Inscripcion.php` el patrón es idéntico al de `Curso.php` (sin threshold).

### Task 7.3: AuditoriaController

**Files:**
- Create: `app/Http/Controllers/Admin/AuditoriaController.php`

- [ ] Crear el controller:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $audits = Audit::with('user')
            ->when($request->modelo, fn($q, $m) =>
                $q->where('auditable_type', 'like', "%{$m}%"))
            ->when($request->accion, fn($q, $a) =>
                $q->where('event', $a))
            ->when($request->usuario_id, fn($q, $u) =>
                $q->where('user_id', $u))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $modelos  = Audit::distinct()->pluck('auditable_type')
            ->map(fn($t) => class_basename($t))->unique()->sort()->values();
        $acciones = Audit::distinct()->pluck('event')->sort()->values();
        $usuarios = User::orderBy('name')->pluck('name', 'id');

        return view('admin.auditoria.index', compact('audits', 'modelos', 'acciones', 'usuarios'));
    }
}
```

### Task 7.4: Vista de auditoría

**Files:**
- Create: `resources/views/admin/auditoria/index.blade.php`

- [ ] Crear la vista:

```blade
@extends('layouts.app')
@section('title', 'Auditoría del Sistema — LMS DyL')
@section('content')
<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Auditoría del Sistema</h1>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap gap-3">
        <select name="modelo" class="form-input w-44">
            <option value="">Todos los modelos</option>
            @foreach($modelos as $modelo)
                <option value="{{ $modelo }}" @selected(request('modelo') === $modelo)>{{ $modelo }}</option>
            @endforeach
        </select>
        <select name="accion" class="form-input w-36">
            <option value="">Todas las acciones</option>
            @foreach($acciones as $accion)
                <option value="{{ $accion }}" @selected(request('accion') === $accion)>{{ ucfirst($accion) }}</option>
            @endforeach
        </select>
        <select name="usuario_id" class="form-input w-52">
            <option value="">Todos los usuarios</option>
            @foreach($usuarios as $id => $nombre)
                <option value="{{ $id }}" @selected(request('usuario_id') == $id)>{{ $nombre }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-primary">Filtrar</button>
        <a href="{{ route('admin.auditoria.index') }}" class="btn-outline">Limpiar</a>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="tbl w-full">
            <thead>
                <tr>
                    <th class="tbl-th">Fecha</th>
                    <th class="tbl-th">Usuario</th>
                    <th class="tbl-th">Acción</th>
                    <th class="tbl-th">Modelo</th>
                    <th class="tbl-th">ID</th>
                    <th class="tbl-th">IP</th>
                    <th class="tbl-th">Campos afectados</th>
                </tr>
            </thead>
            <tbody>
            @forelse($audits as $audit)
                <tr class="tbl-row">
                    <td class="tbl-td text-gray-500 text-xs whitespace-nowrap">
                        {{ $audit->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="tbl-td text-sm font-medium">{{ $audit->user?->name ?? 'Sistema' }}</td>
                    <td class="tbl-td">
                        <span class="badge {{ match($audit->event) {
                            'created' => 'badge-green',
                            'deleted' => 'badge-red',
                            default   => 'badge-blue',
                        } }}">{{ ucfirst($audit->event) }}</span>
                    </td>
                    <td class="tbl-td text-sm font-medium">{{ class_basename($audit->auditable_type) }}</td>
                    <td class="tbl-td text-gray-400 text-xs">#{{ $audit->auditable_id }}</td>
                    <td class="tbl-td text-gray-400 text-xs">{{ $audit->ip_address ?? '—' }}</td>
                    <td class="tbl-td">
                        @if($audit->new_values)
                        <details class="text-xs cursor-pointer">
                            <summary class="text-blue-600 hover:text-blue-800 select-none">
                                {{ count($audit->new_values) }} campo(s)
                            </summary>
                            <pre class="bg-gray-50 rounded p-2 mt-1 text-xs overflow-x-auto max-w-xs whitespace-pre-wrap">{{ implode(', ', array_keys($audit->new_values)) }}</pre>
                        </details>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="tbl-td text-center text-gray-400 py-10">No hay registros de auditoría.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $audits->links() }}</div>
</div>
@endsection
```

### Task 7.5: Ruta de auditoría y enlace en navbar

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`

- [ ] Agregar la ruta dentro del grupo admin (junto a la ruta de usuarios):

```php
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class)
        ->except(['show']);
    Route::get('auditoria',
        [\App\Http\Controllers\Admin\AuditoriaController::class, 'index'])
        ->name('auditoria.index');
});
```

- [ ] Agregar enlace "Auditoría" en el navbar para admin (junto al enlace de Usuarios):

```blade
@if(auth()->user()->esAdmin())
    <a href="{{ route('admin.auditoria.index') }}"
       class="{{ request()->routeIs('admin.auditoria.*') ? 'nav-link-active' : 'nav-link' }}">
        Auditoría
    </a>
@endif
```

- [ ] Ejecutar tests:

```bash
php artisan test
```

Expected: todos los tests pasan.

- [ ] Commit:

```bash
git add app/Http/Controllers/Admin/AuditoriaController.php \
        app/Models/Curso.php app/Models/User.php app/Models/Actividad.php \
        app/Models/RespuestaEstudiante.php app/Models/Inscripcion.php \
        resources/views/admin/auditoria/ \
        routes/web.php resources/views/layouts/app.blade.php \
        config/audit.php database/migrations/*audit*
git commit -m "feat: audit trail for Curso, User, Actividad, Respuesta, Inscripcion"
```

---

## FASE 8: Breadcrumbs

### Task 8.1: Instalar diglactic/laravel-breadcrumbs

- [ ] Instalar el paquete:

```bash
composer require diglactic/laravel-breadcrumbs
```

### Task 8.2: Publicar config y definir breadcrumbs

- [ ] Publicar configuración:

```bash
php artisan vendor:publish --provider="Diglactic\Breadcrumbs\ServiceProvider" --tag=breadcrumbs-config
```

- [ ] Editar `config/breadcrumbs.php` para usar la vista Tailwind incluida:

```php
'view' => 'breadcrumbs::tailwind',
```

**Files:**
- Create: `routes/breadcrumbs.php`

- [ ] Crear el archivo de definiciones:

```php
<?php

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as Trail;

// Dashboard
Breadcrumbs::for('dashboard', fn(Trail $t) =>
    $t->push('Inicio', route('dashboard'))
);

// Cursos
Breadcrumbs::for('cursos.index', fn(Trail $t) =>
    $t->parent('dashboard')->push('Cursos', route('cursos.index'))
);
Breadcrumbs::for('cursos.create', fn(Trail $t) =>
    $t->parent('cursos.index')->push('Nuevo Curso')
);
Breadcrumbs::for('cursos.show', fn(Trail $t, $curso) =>
    $t->parent('cursos.index')->push($curso->titulo, route('cursos.show', $curso))
);
Breadcrumbs::for('cursos.edit', fn(Trail $t, $curso) =>
    $t->parent('cursos.show', $curso)->push('Editar')
);

// Lecciones
Breadcrumbs::for('lecciones.show', fn(Trail $t, $leccion) =>
    $t->parent('cursos.show', $leccion->modulo->curso)->push($leccion->titulo)
);
Breadcrumbs::for('lecciones.edit', fn(Trail $t, $leccion) =>
    $t->parent('lecciones.show', $leccion)->push('Editar')
);
Breadcrumbs::for('lecciones.create', fn(Trail $t, $modulo) =>
    $t->parent('cursos.edit', $modulo->curso)->push('Nueva Lección')
);

// Actividades
Breadcrumbs::for('actividades.show', fn(Trail $t, $actividad) =>
    $t->parent('lecciones.show', $actividad->leccion)->push($actividad->titulo)
);
Breadcrumbs::for('actividades.edit', fn(Trail $t, $actividad) =>
    $t->parent('cursos.edit', $actividad->leccion->modulo->curso)->push('Editar Actividad')
);

// Calificaciones
Breadcrumbs::for('calificaciones.index', fn(Trail $t) =>
    $t->parent('dashboard')->push('Calificaciones', route('calificaciones.index'))
);
Breadcrumbs::for('calificaciones.show', fn(Trail $t, $respuesta) =>
    $t->parent('calificaciones.index')->push('Detalle #' . $respuesta->id)
);
Breadcrumbs::for('mis-calificaciones', fn(Trail $t) =>
    $t->parent('dashboard')->push('Mis Calificaciones', route('mis-calificaciones'))
);

// Certificados
Breadcrumbs::for('certificados.mis-certificados', fn(Trail $t) =>
    $t->parent('dashboard')->push('Mis Certificados', route('certificados.mis-certificados'))
);

// Reportes
Breadcrumbs::for('reportes.index', fn(Trail $t) =>
    $t->parent('dashboard')->push('Reportes', route('reportes.index'))
);
Breadcrumbs::for('reportes.curso', fn(Trail $t, $curso) =>
    $t->parent('reportes.index')->push($curso->titulo)
);
Breadcrumbs::for('reportes.estudiante', fn(Trail $t, $usuario) =>
    $t->parent('reportes.index')->push($usuario->name)
);

// Admin — Usuarios
Breadcrumbs::for('admin.usuarios.index', fn(Trail $t) =>
    $t->parent('dashboard')->push('Usuarios', route('admin.usuarios.index'))
);
Breadcrumbs::for('admin.usuarios.create', fn(Trail $t) =>
    $t->parent('admin.usuarios.index')->push('Nuevo Usuario')
);
Breadcrumbs::for('admin.usuarios.edit', fn(Trail $t, $usuario) =>
    $t->parent('admin.usuarios.index')->push($usuario->name)
);

// Admin — Auditoría
Breadcrumbs::for('admin.auditoria.index', fn(Trail $t) =>
    $t->parent('dashboard')->push('Auditoría', route('admin.auditoria.index'))
);
```

### Task 8.3: Cargar breadcrumbs en AppServiceProvider

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] Agregar en el método `boot()`:

```php
public function boot(): void
{
    if (file_exists(base_path('routes/breadcrumbs.php'))) {
        require base_path('routes/breadcrumbs.php');
    }
}
```

### Task 8.4: Integrar breadcrumbs en el layout

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] Agregar soporte para breadcrumbs en el layout. Agregar justo dentro del `<main>` antes de `@yield('content')`:

```blade
@hasSection('breadcrumbs')
<nav aria-label="Breadcrumb" class="mb-4">
    @yield('breadcrumbs')
</nav>
@endif
```

### Task 8.5: Agregar breadcrumbs en vistas principales

**Files:**
- Modify: `resources/views/cursos/index.blade.php`
- Modify: `resources/views/cursos/show.blade.php`
- Modify: `resources/views/cursos/create.blade.php`
- Modify: `resources/views/cursos/edit.blade.php`
- Modify: `resources/views/lecciones/show.blade.php`
- Modify: `resources/views/lecciones/edit.blade.php`
- Modify: `resources/views/actividades/show.blade.php`
- Modify: `resources/views/calificaciones/index.blade.php`
- Modify: `resources/views/reportes/index.blade.php`
- Modify: `resources/views/reportes/curso.blade.php`
- Modify: `resources/views/admin/usuarios/index.blade.php`
- Modify: `resources/views/admin/usuarios/create.blade.php`
- Modify: `resources/views/admin/usuarios/edit.blade.php`
- Modify: `resources/views/admin/auditoria/index.blade.php`

- [ ] Para **cada vista**, agregar después de `@section('title', ...)` la sección de breadcrumbs. El patrón varía según si la ruta necesita pasar un modelo o no:

Vistas sin modelo (rutas simples):
```blade
@section('breadcrumbs')
    {{ Breadcrumbs::render('nombre.de.la.ruta') }}
@endsection
```

Vistas con modelo (ejemplo para `cursos/show.blade.php`):
```blade
@section('breadcrumbs')
    {{ Breadcrumbs::render('cursos.show', $curso) }}
@endsection
```

Vistas con modelo (ejemplo para `lecciones/show.blade.php`):
```blade
@section('breadcrumbs')
    {{ Breadcrumbs::render('lecciones.show', $leccion) }}
@endsection
```

Vistas con modelo (ejemplo para `admin/usuarios/edit.blade.php`):
```blade
@section('breadcrumbs')
    {{ Breadcrumbs::render('admin.usuarios.edit', $usuario) }}
@endsection
```

- [ ] Compilar assets:

```bash
npm run build
```

- [ ] Ejecutar tests:

```bash
php artisan test
```

Expected: todos los tests pasan.

- [ ] Commit:

```bash
git add routes/breadcrumbs.php app/Providers/AppServiceProvider.php \
        resources/views/layouts/app.blade.php \
        resources/views/cursos/ resources/views/lecciones/ \
        resources/views/actividades/ resources/views/calificaciones/ \
        resources/views/reportes/ resources/views/admin/ \
        config/breadcrumbs.php
git commit -m "feat: breadcrumbs navigation across all main views"
```

---

## VERIFICACIÓN FINAL

- [ ] Ejecutar la suite completa:

```bash
php artisan test --verbose
```

Expected: todos los tests pasan (56+ existentes + 5 nuevos de admin usuarios).

- [ ] Verificar la app en el navegador navegando a http://localhost:8000:
  - [ ] Login con admin@dyl-quality.test funciona
  - [ ] Dashboard admin muestra gráficos (cursos por estado + inscripciones por mes)
  - [ ] Navbar admin tiene "Usuarios" y "Auditoría"
  - [ ] `/admin/usuarios` lista, crea, edita y elimina usuarios
  - [ ] Reportes tienen botón "Excel" y descarga el .xlsx
  - [ ] Dashboard instructor muestra gráfico de progreso por curso
  - [ ] Perfil de usuario tiene sección 2FA
  - [ ] Breadcrumbs aparecen en todas las páginas
  - [ ] Log de auditoría registra cambios en modelos

- [ ] Commit de cierre:

```bash
git add .
git commit -m "chore: LMS DyL Quality — todas las features completadas (v1.1)"
```

---

*Plan generado: 2026-05-11*
*Proyecto: LMS DyL Quality Consulting*
*Stack: Laravel 12 + PHP 8.2 + MySQL + Tailwind CSS + Alpine.js*
*Tests base: 56 tests, 118 assertions*
