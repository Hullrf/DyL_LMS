<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Rol;
use App\Models\Categoria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole      = Rol::firstOrCreate(['nombre' => 'Administrador'], ['descripcion' => 'Acceso total al sistema']);
        $instructorRole = Rol::firstOrCreate(['nombre' => 'Instructor'],    ['descripcion' => 'Puede crear y editar cursos']);
        $studentRole    = Rol::firstOrCreate(['nombre' => 'Estudiante'],    ['descripcion' => 'Puede ver cursos e inscribirse']);

        // Categorías por defecto
        $categorias = [
            ['nombre' => 'Gestión de Calidad', 'color' => '#4F46E5'],
            ['nombre' => 'Seguridad y Salud',  'color' => '#059669'],
            ['nombre' => 'Normas ISO',         'color' => '#D97706'],
            ['nombre' => 'Liderazgo',          'color' => '#7C3AED'],
            ['nombre' => 'Auditoría',          'color' => '#DC2626'],
        ];
        foreach ($categorias as $cat) {
            Categoria::firstOrCreate(['nombre' => $cat['nombre']], [
                'slug'  => Str::slug($cat['nombre']),
                'color' => $cat['color'],
            ]);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@dyl-quality.test'],
            [
                'name'     => 'David Admin',
                'password' => Hash::make('password123'),
                'empresa'  => 'DyL Quality Consulting',
                'estado'   => 'activo',
            ]
        );
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $instructor = User::firstOrCreate(
            ['email' => 'instructor@dyl-quality.test'],
            [
                'name'     => 'Instructor Test',
                'password' => Hash::make('password123'),
                'empresa'  => 'DyL Quality Consulting',
                'estado'   => 'activo',
            ]
        );
        $instructor->roles()->syncWithoutDetaching([$instructorRole->id]);

        $student = User::firstOrCreate(
            ['email' => 'student@dyl-quality.test'],
            [
                'name'     => 'Estudiante Test',
                'password' => Hash::make('password123'),
                'empresa'  => 'Empresa Cliente',
                'estado'   => 'activo',
            ]
        );
        $student->roles()->syncWithoutDetaching([$studentRole->id]);

        echo "\n Usuarios creados:\n";
        echo "  - Admin: admin@dyl-quality.test / password123\n";
        echo "  - Instructor: instructor@dyl-quality.test / password123\n";
        echo "  - Estudiante: student@dyl-quality.test / password123\n\n";
    }
}
