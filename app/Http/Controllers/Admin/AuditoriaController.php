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
