<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole      = Rol::firstOrCreate(['nombre' => 'Administrador'], ['descripcion' => 'Acceso total al sistema']);
        $instructorRole = Rol::firstOrCreate(['nombre' => 'Instructor'],    ['descripcion' => 'Puede crear y editar cursos']);
        $studentRole    = Rol::firstOrCreate(['nombre' => 'Estudiante'],    ['descripcion' => 'Puede ver cursos e inscribirse']);

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
