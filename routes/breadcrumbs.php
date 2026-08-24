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
Breadcrumbs::for('calificaciones.curso', fn(Trail $t, $curso) =>
    $t->parent('calificaciones.index')->push($curso->titulo, route('calificaciones.curso', $curso))
);
Breadcrumbs::for('calificaciones.show', fn(Trail $t, $respuesta) =>
    $t->parent('calificaciones.curso', $respuesta->actividad->leccion->modulo->curso)->push('Detalle #' . $respuesta->id)
);
Breadcrumbs::for('mis-calificaciones', fn(Trail $t) =>
    $t->parent('dashboard')->push('Mis Calificaciones', route('calificaciones.mis'))
);

// Certificados
Breadcrumbs::for('certificados.mis-certificados', fn(Trail $t) =>
    $t->parent('dashboard')->push('Mis Certificados', route('certificados.mis'))
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

// Admin — Categorías
Breadcrumbs::for('admin.categorias.index', fn(Trail $t) =>
    $t->parent('dashboard')->push('Categorías', route('admin.categorias.index'))
);
Breadcrumbs::for('admin.categorias.create', fn(Trail $t) =>
    $t->parent('admin.categorias.index')->push('Nueva Categoría')
);
Breadcrumbs::for('admin.categorias.edit', fn(Trail $t, $categoria) =>
    $t->parent('admin.categorias.index')->push($categoria->nombre)
);

// Admin — Auditoría
Breadcrumbs::for('admin.auditoria.index', fn(Trail $t) =>
    $t->parent('dashboard')->push('Auditoría', route('admin.auditoria.index'))
);
