<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\User;
use Database\Seeders\CursosDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursosDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuariosBase(): void
    {
        $instructorRole = Rol::factory()->instructor()->create();
        $studentRole = Rol::factory()->estudiante()->create();

        $instructor = User::factory()->create(['email' => 'instructor@dyl-quality.test', 'estado' => 'activo']);
        $instructor->roles()->attach($instructorRole);

        $student = User::factory()->create(['email' => 'student@dyl-quality.test', 'estado' => 'activo']);
        $student->roles()->attach($studentRole);
    }

    public function test_correr_el_seeder_dos_veces_no_lanza_error_de_duplicado(): void
    {
        $this->crearUsuariosBase();

        // Simula lo que pasa en Railway: el startCommand corre db:seed en cada
        // reinicio del contenedor (restartPolicyType ON_FAILURE), así que el
        // seeder debe poder correr repetidamente sin romperse.
        (new CursosDemoSeeder())->run();
        (new CursosDemoSeeder())->run();

        $this->assertSame(3, Curso::where('titulo', 'Fundamentos de ISO 9001:2015')
            ->orWhere('titulo', 'Seguridad y Salud Ocupacional')
            ->orWhere('titulo', 'Liderazgo Efectivo en Equipos de Calidad')
            ->count());

        // Curso 1 (3 módulos) y curso 2 (2 módulos) crean sus hijos solo cuando
        // el curso es nuevo (wasRecentlyCreated); esto verifica que la segunda
        // corrida no los duplicó.
        $this->assertSame(5, Modulo::count());
    }

    public function test_correr_el_seeder_una_vez_crea_los_tres_cursos_demo(): void
    {
        $this->crearUsuariosBase();

        (new CursosDemoSeeder())->run();

        $this->assertDatabaseHas('cursos', ['titulo' => 'Fundamentos de ISO 9001:2015', 'estado' => 'publicado']);
        $this->assertDatabaseHas('cursos', ['titulo' => 'Seguridad y Salud Ocupacional', 'estado' => 'publicado']);
        $this->assertDatabaseHas('cursos', ['titulo' => 'Liderazgo Efectivo en Equipos de Calidad', 'estado' => 'borrador']);
    }
}
