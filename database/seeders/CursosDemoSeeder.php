<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Curso;
use App\Models\Modulo;
use App\Models\Leccion;
use App\Models\Actividad;
use App\Models\Pregunta;
use App\Models\Opcion;
use App\Models\Inscripcion;
use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CursosDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Curso::where('titulo', 'Fundamentos de ISO 9001:2015')->exists()) {
            echo "\n Cursos demo ya existen, omitiendo...\n";
            return;
        }

        $instructor = User::where('email', 'instructor@dyl-quality.test')->first();
        $estudiante = User::where('email', 'student@dyl-quality.test')->first();
        if (!$instructor || !$estudiante) return;

        $categorias = Categoria::pluck('id', 'nombre');

        // ─── Curso 1: ISO 9001 ──────────────────────────────
        $c1 = Curso::create([
            'titulo'         => 'Fundamentos de ISO 9001:2015',
            'descripcion'    => 'Aprende los principios de gestión de calidad y los requisitos de la norma ISO 9001:2015. Ideal para auditores internos y líderes de proceso.',
            'duracion_horas' => 20,
            'estado'         => 'publicado',
            'created_by'     => $instructor->id,
            'categoria_id'   => $categorias['Normas ISO'] ?? null,
            'orden'          => 0,
        ]);

        $m1 = Modulo::create(['curso_id' => $c1->id, 'titulo' => 'Contexto y Liderazgo',   'orden' => 0, 'duracion_horas' => 6]);
        $m2 = Modulo::create(['curso_id' => $c1->id, 'titulo' => 'Planificación y Soporte', 'orden' => 1, 'duracion_horas' => 8]);
        $m3 = Modulo::create(['curso_id' => $c1->id, 'titulo' => 'Evaluación y Mejora',     'orden' => 2, 'duracion_horas' => 6]);

        $l1 = Leccion::create(['modulo_id' => $m1->id, 'titulo' => 'Introducción a ISO 9001',       'contenido_html' => '<p>La <strong>ISO 9001:2015</strong> es la norma internacional para Sistemas de Gestión de Calidad...</p><p>En esta lección aprenderás:</p><ul><li>Historia de la norma</li><li>Principios de gestión de calidad</li><li>Estructura de alto nivel</li></ul>', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'orden' => 0, 'duracion_minutos' => 30, 'tipo' => 'video']);
        $l2 = Leccion::create(['modulo_id' => $m2->id, 'titulo' => 'Planificación del SGC',         'contenido_html' => '<p>La planificación es clave para un SGC efectivo. Abarca:</p><ul><li>Identificación de partes interesadas</li><li>Alcance del sistema</li><li>Objetivos de calidad</li></ul>', 'orden' => 0, 'duracion_minutos' => 45, 'tipo' => 'mixto']);
        $l3 = Leccion::create(['modulo_id' => $m3->id, 'titulo' => 'Auditoría Interna',             'contenido_html' => '<p>La auditoría interna verifica la conformidad del SGC. Temas clave:</p><ul><li>Planificación de auditorías</li><li>Ejecución y hallazgos</li><li>Informe y seguimiento</li></ul>', 'orden' => 0, 'duracion_minutos' => 40, 'tipo' => 'texto']);

        $act1 = Actividad::create(['leccion_id' => $l1->id, 'tipo' => 'cuestionario', 'titulo' => 'Quiz: Principios ISO 9001',     'puntaje_maximo' => 20,  'orden' => 0, 'duracion_minutos' => 15, 'es_obligatoria' => true]);
        $act2 = Actividad::create(['leccion_id' => $l2->id, 'tipo' => 'ensayo',       'titulo' => 'Ensayo: Objetivos de Calidad',   'puntaje_maximo' => 30,  'orden' => 0, 'duracion_minutos' => null, 'es_obligatoria' => true]);
        $act3 = Actividad::create(['leccion_id' => $l3->id, 'tipo' => 'tarea',        'titulo' => 'Plan de Auditoría',              'puntaje_maximo' => 50,  'orden' => 0, 'duracion_minutos' => null, 'es_obligatoria' => true]);
        $act4 = Actividad::create(['leccion_id' => $l3->id, 'tipo' => 'encuesta',     'titulo' => 'Encuesta de satisfacción',        'puntaje_maximo' => null, 'orden' => 1, 'es_obligatoria' => false]);

        $p1 = Pregunta::create(['actividad_id' => $act1->id, 'pregunta_texto' => '¿Cuántos principios tiene la gestión de calidad según ISO 9001?', 'tipo' => 'opcion_multiple', 'puntaje' => 5, 'orden' => 0]);
        Opcion::create(['pregunta_id' => $p1->id, 'texto' => '5',   'es_correcta' => false, 'orden' => 0]);
        Opcion::create(['pregunta_id' => $p1->id, 'texto' => '7',   'es_correcta' => true,  'orden' => 1]);
        Opcion::create(['pregunta_id' => $p1->id, 'texto' => '8',   'es_correcta' => false, 'orden' => 2]);
        Opcion::create(['pregunta_id' => $p1->id, 'texto' => '10',  'es_correcta' => false, 'orden' => 3]);

        $p2 = Pregunta::create(['actividad_id' => $act1->id, 'pregunta_texto' => 'El enfoque basado en procesos es uno de los principios.', 'tipo' => 'verdadero_falso', 'puntaje' => 5, 'orden' => 1]);
        Opcion::create(['pregunta_id' => $p2->id, 'texto' => 'Verdadero', 'es_correcta' => true,  'orden' => 0]);
        Opcion::create(['pregunta_id' => $p2->id, 'texto' => 'Falso',     'es_correcta' => false, 'orden' => 1]);

        $p3 = Pregunta::create(['actividad_id' => $act1->id, 'pregunta_texto' => 'Selecciona los principios correctos:', 'tipo' => 'opcion_multiple', 'seleccion_multiple' => true, 'puntaje' => 10, 'orden' => 2]);
        Opcion::create(['pregunta_id' => $p3->id, 'texto' => 'Enfoque al cliente',       'es_correcta' => true,  'orden' => 0]);
        Opcion::create(['pregunta_id' => $p3->id, 'texto' => 'Liderazgo',                'es_correcta' => true,  'orden' => 1]);
        Opcion::create(['pregunta_id' => $p3->id, 'texto' => 'Maximización de ingresos', 'es_correcta' => false, 'orden' => 2]);
        Opcion::create(['pregunta_id' => $p3->id, 'texto' => 'Mejora continua',          'es_correcta' => true,  'orden' => 3]);

        // ─── Curso 2: Seguridad ────────────────────────────
        $c2 = Curso::create([
            'titulo' => 'Seguridad y Salud Ocupacional',
            'descripcion' => 'Fundamentos de seguridad industrial, identificación de riesgos y medidas preventivas en el entorno laboral.',
            'duracion_horas' => 15,
            'estado' => 'publicado',
            'created_by' => $instructor->id,
            'categoria_id' => $categorias['Seguridad y Salud'] ?? null,
            'orden' => 1,
        ]);

        $m4 = Modulo::create(['curso_id' => $c2->id, 'titulo' => 'Riesgos Laborales', 'orden' => 0, 'duracion_horas' => 8]);
        $m5 = Modulo::create(['curso_id' => $c2->id, 'titulo' => 'Prevención y EPP',  'orden' => 1, 'duracion_horas' => 7]);

        Leccion::create(['modulo_id' => $m4->id, 'titulo' => 'Identificación de Peligros', 'contenido_html' => '<p>Todo lugar de trabajo tiene riesgos. Esta lección cubre:</p><ul><li>Tipos de peligros (físicos, químicos, biológicos)</li><li>Metodología de evaluación</li><li>Matriz de riesgos</li></ul>', 'orden' => 0, 'duracion_minutos' => 35, 'tipo' => 'mixto']);
        Leccion::create(['modulo_id' => $m5->id, 'titulo' => 'Uso de EPP',                  'contenido_html' => '<p>El Equipo de Protección Personal es la última barrera. Incluye:</p><ul><li>Cascos y protección craneal</li><li>Protección ocular y auditiva</li><li>Calzado de seguridad</li></ul>', 'orden' => 0, 'duracion_minutos' => 25, 'tipo' => 'video']);
        Actividad::create(['leccion_id' => Leccion::where('modulo_id', $m4->id)->first()->id, 'tipo' => 'cuestionario', 'titulo' => 'Quiz: Identificación de Riesgos', 'puntaje_maximo' => 15, 'orden' => 0, 'es_obligatoria' => true]);
        Actividad::create(['leccion_id' => Leccion::where('modulo_id', $m5->id)->first()->id, 'tipo' => 'practica', 'titulo' => 'Inspección de EPP', 'puntaje_maximo' => 20, 'orden' => 0, 'es_obligatoria' => true]);

        // ─── Curso 3: Liderazgo (borrador) ─────────────────
        Curso::create([
            'titulo'         => 'Liderazgo Efectivo en Equipos de Calidad',
            'descripcion'    => 'Desarrolla habilidades de liderazgo para guiar equipos hacia la excelencia operativa y la mejora continua.',
            'duracion_horas' => 12,
            'estado'         => 'borrador',
            'created_by'     => $instructor->id,
            'categoria_id'   => $categorias['Liderazgo'] ?? null,
            'orden'          => 2,
        ]);

        // Inscribir estudiante en los cursos publicados
        Inscripcion::firstOrCreate(
            ['user_id' => $estudiante->id, 'curso_id' => $c1->id],
            ['fecha_inicio' => now()->subDays(7)->toDateString(), 'estado' => 'en_progreso']
        );
        Inscripcion::firstOrCreate(
            ['user_id' => $estudiante->id, 'curso_id' => $c2->id],
            ['fecha_inicio' => now()->subDays(3)->toDateString(), 'estado' => 'en_progreso']
        );

        echo "\n Cursos demo creados: ISO 9001 (publicado), Seguridad (publicado), Liderazgo (borrador)\n";
        echo "   Estudiante inscrito en ISO 9001 y Seguridad\n\n";
    }
}
