<?php

namespace Tests\Concerns;

use App\Models\Actividad;
use App\Models\Categoria;
use App\Models\CriterioRubrica;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\NivelCriterio;
use App\Models\Opcion;
use App\Models\Pregunta;
use App\Models\RecursoActividad;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

trait ArmaCursoCompleto
{
    protected function usuarioConRol(string $rol): User
    {
        $r = Rol::firstOrCreate(['nombre' => $rol], ['descripcion' => $rol]);
        $u = User::factory()->create(['estado' => 'activo']);
        $u->roles()->attach($r);
        return $u;
    }

    protected function instructor(): User  { return $this->usuarioConRol('Instructor'); }
    protected function admin(): User       { return $this->usuarioConRol('Administrador'); }
    protected function estudiante(): User  { return $this->usuarioConRol('Estudiante'); }

    /** Curso con módulo, lección (con imagen del editor), actividad, pregunta, recurso y rúbrica. */
    protected function cursoCompleto(User $dueno): Curso
    {
        Storage::fake('public');
        Storage::disk('public')->put('editor/2026-10/foto.png', 'PNGDATA');
        Storage::disk('public')->put('cursos/origen/portada.jpg', 'PORTADA');
        Storage::disk('public')->put('cursos/origen/preguntas/p.png', 'PREG');
        Storage::disk('public')->put('cursos/origen/recursos/guia.pdf', 'PDFDATA');

        $categoria = Categoria::create(['nombre' => 'Calidad', 'slug' => 'calidad']);

        $curso = Curso::factory()->publicado()->diplomado()->create([
            'created_by'       => $dueno->id,
            'titulo'           => 'ISO 9001',
            'categoria_id'     => $categoria->id,
            'imagen_portada'   => 'cursos/origen/portada.jpg',
            'nota_aprobatoria' => 70,
        ]);

        $modulo  = Modulo::factory()->create(['curso_id' => $curso->id, 'orden' => 0, 'titulo' => 'Modulo 1']);
        $leccion = Leccion::factory()->create([
            'modulo_id'      => $modulo->id,
            'orden'          => 0,
            'contenido_html' => '<p>Hola</p>'
                . '<img src="' . asset('storage/editor/2026-10/foto.png') . '">'
                . '<img src="https://externo.test/storage/x.png">',
        ]);
        $actividad = Actividad::factory()->cuestionario()->create([
            'leccion_id'          => $leccion->id,
            'intentos_permitidos' => 3,
            'usa_rubrica'         => true,
            'fecha_apertura'      => now(),
            'fecha_cierre'        => now()->addDay(),
        ]);
        $pregunta = Pregunta::factory()->create([
            'actividad_id' => $actividad->id,
            'imagen_path'  => 'cursos/origen/preguntas/p.png',
            'orden'        => 0,
        ]);
        Opcion::factory()->correcta()->create(['pregunta_id' => $pregunta->id, 'orden' => 0, 'explicacion' => 'Porque si']);
        Opcion::factory()->create(['pregunta_id' => $pregunta->id, 'orden' => 1]);
        RecursoActividad::create([
            'actividad_id'            => $actividad->id,
            'tipo'                    => 'documento',
            'titulo'                  => 'Guia',
            'archivo_path'            => 'cursos/origen/recursos/guia.pdf',
            'archivo_nombre_original' => 'Guía ISO.pdf',
            'orden'                   => 0,
        ]);
        $criterio = CriterioRubrica::create(['actividad_id' => $actividad->id, 'nombre' => 'Claridad', 'orden' => 0]);
        NivelCriterio::create(['criterio_id' => $criterio->id, 'descripcion' => 'Excelente', 'puntos' => 5, 'orden' => 0]);

        return $curso;
    }
}
