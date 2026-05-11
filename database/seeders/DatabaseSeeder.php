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
        $adminRole = Rol::create(['nombre' => 'Administrador', 'descripcion' => 'Acceso total al sistema']);
        $instructorRole = Rol::create(['nombre' => 'Instructor', 'descripcion' => 'Puede crear y editar cursos']);
        $studentRole = Rol::create(['nombre' => 'Estudiante', 'descripcion' => 'Puede ver cursos e inscribirse']);

        $admin = User::create([
            'name'     => 'David Admin',
            'email'    => 'admin@dyl-quality.test',
            'password' => Hash::make('password123'),
            'empresa'  => 'DyL Quality Consulting',
            'estado'   => 'activo',
        ]);
        $admin->roles()->attach($adminRole);

        $instructor = User::create([
            'name'     => 'Instructor Test',
            'email'    => 'instructor@dyl-quality.test',
            'password' => Hash::make('password123'),
            'empresa'  => 'DyL Quality Consulting',
            'estado'   => 'activo',
        ]);
        $instructor->roles()->attach($instructorRole);

        $student = User::create([
            'name'     => 'Estudiante Test',
            'email'    => 'student@dyl-quality.test',
            'password' => Hash::make('password123'),
            'empresa'  => 'Empresa Cliente',
            'estado'   => 'activo',
        ]);
        $student->roles()->attach($studentRole);

        echo "\n Usuarios creados:\n";
        echo "  - Admin: admin@dyl-quality.test / password123\n";
        echo "  - Instructor: instructor@dyl-quality.test / password123\n";
        echo "  - Estudiante: student@dyl-quality.test / password123\n\n";
    }
}
