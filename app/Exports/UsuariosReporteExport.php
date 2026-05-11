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
