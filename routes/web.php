<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ModuloController;
use App\Http\Controllers\LeccionController;
use App\Http\Controllers\ActividadController;
use App\Http\Controllers\ImportacionCuestionarioController;
use App\Http\Controllers\PreguntaController;
use App\Http\Controllers\OpcionController;
use App\Http\Controllers\RespuestaEstudianteController;
use App\Http\Controllers\CalificacionController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RecursoActividadController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\AnuncioController;
use App\Http\Controllers\ForoController;
use App\Http\Controllers\MensajeController;
use App\Http\Controllers\NotificacionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect('/dashboard'));

Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Cursos - rutas estáticas primero para evitar conflicto con {curso}
    Route::get('/cursos', [CursoController::class, 'index'])->name('cursos.index');

    Route::middleware('instructor')->group(function () {
        Route::get('/cursos/crear', [CursoController::class, 'create'])->name('cursos.create');
        Route::post('/cursos', [CursoController::class, 'store'])->name('cursos.store');
        Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
    });

    // Rutas con parámetro {curso} después de las estáticas
    Route::get('/cursos/{curso}', [CursoController::class, 'show'])->name('cursos.show');

    Route::middleware('instructor')->group(function () {
        Route::get('/cursos/{curso}/inscribir-estudiantes', [CursoController::class, 'inscripcionMasiva'])->name('cursos.inscripcion-masiva');
        Route::post('/cursos/{curso}/inscribir-estudiantes', [CursoController::class, 'procesarInscripcionMasiva']);
    });

    Route::middleware('instructor')->group(function () {
        Route::get('/cursos/{curso}/editar', [CursoController::class, 'edit'])->name('cursos.edit');
        Route::put('/cursos/{curso}', [CursoController::class, 'update'])->name('cursos.update');
        Route::delete('/cursos/{curso}', [CursoController::class, 'destroy'])->name('cursos.destroy');
    });

    // Módulos
    Route::middleware('instructor')->group(function () {
        Route::post('/cursos/{curso}/modulos', [ModuloController::class, 'store'])->name('modulos.store');
        Route::get('/modulos/{modulo}/editar', [ModuloController::class, 'edit'])->name('modulos.edit');
        Route::put('/modulos/{modulo}', [ModuloController::class, 'update'])->name('modulos.update');
        Route::delete('/modulos/{modulo}', [ModuloController::class, 'destroy'])->name('modulos.destroy');
        Route::post('/cursos/{curso}/modulos/reordenar', [ModuloController::class, 'reordenar'])->name('modulos.reordenar');
    });

    // Uploads del editor (solo instructor)
    Route::middleware('instructor')->group(function () {
        Route::post('/upload/imagen-editor', [UploadController::class, 'editorImagen'])->name('upload.imagen-editor');
    });

    // Lecciones — ver/completar (todos), editar (solo instructor)
    Route::get('/lecciones/{leccion}', [LeccionController::class, 'show'])->name('lecciones.show');
    Route::post('/lecciones/{leccion}/completar', [LeccionController::class, 'completar'])->name('lecciones.completar');

    Route::middleware('instructor')->group(function () {
        Route::get('/modulos/{modulo}/lecciones/crear', [LeccionController::class, 'create'])->name('lecciones.create');
        Route::post('/modulos/{modulo}/lecciones', [LeccionController::class, 'store'])->name('lecciones.store');
        Route::get('/lecciones/{leccion}/editar', [LeccionController::class, 'edit'])->name('lecciones.edit');
        Route::put('/lecciones/{leccion}', [LeccionController::class, 'update'])->name('lecciones.update');
        Route::delete('/lecciones/{leccion}', [LeccionController::class, 'destroy'])->name('lecciones.destroy');
    });

    // Actividades — instructor crea/edita, todos pueden ver
    Route::get('/actividades/{actividad}', [ActividadController::class, 'show'])->name('actividades.show');
    Route::post('/actividades/{actividad}/completar', [ActividadController::class, 'completar'])->name('actividades.completar');
    Route::post('/actividades/{actividad}/iniciar-intento', [ActividadController::class, 'iniciarIntento'])->name('actividades.iniciarIntento');
    Route::middleware('instructor')->group(function () {
        Route::get('/lecciones/{leccion}/actividades/crear', [ActividadController::class, 'create'])->name('actividades.create');
        Route::post('/lecciones/{leccion}/actividades', [ActividadController::class, 'store'])->name('actividades.store');
        Route::get('/actividades/{actividad}/editar', [ActividadController::class, 'edit'])->name('actividades.edit');
        Route::put('/actividades/{actividad}', [ActividadController::class, 'update'])->name('actividades.update');
        Route::delete('/actividades/{actividad}', [ActividadController::class, 'destroy'])->name('actividades.destroy');
        Route::post('/cursos/{curso}/actividades/mover', [ActividadController::class, 'mover'])->name('actividades.mover');
    });

    // Recursos de actividades
    Route::get('/recursos/{recurso}/descargar', [RecursoActividadController::class, 'descargar'])->name('recursos.descargar');
    Route::middleware('instructor')->group(function () {
        Route::post('/actividades/{actividad}/recursos', [RecursoActividadController::class, 'store'])->name('recursos.store');
        Route::delete('/recursos/{recurso}', [RecursoActividadController::class, 'destroy'])->name('recursos.destroy');
    });

    // Preguntas y Opciones
    Route::middleware('instructor')->group(function () {
        Route::post('/actividades/{actividad}/preguntas', [PreguntaController::class, 'store'])->name('preguntas.store');
        Route::post('/actividades/{actividad}/preguntas/importar', [ImportacionCuestionarioController::class, 'store'])->name('preguntas.importar');
        Route::put('/preguntas/{pregunta}', [PreguntaController::class, 'update'])->name('preguntas.update');
        Route::delete('/preguntas/{pregunta}', [PreguntaController::class, 'destroy'])->name('preguntas.destroy');
        Route::delete('/preguntas/{pregunta}/imagen', [PreguntaController::class, 'destroyImagen'])->name('preguntas.imagen.destroy');
        Route::post('/preguntas/{pregunta}/opciones', [OpcionController::class, 'store'])->name('opciones.store');
        Route::delete('/opciones/{opcion}', [OpcionController::class, 'destroy'])->name('opciones.destroy');
        Route::put('/opciones/{opcion}/marcar-correcta', [OpcionController::class, 'marcarCorrecta'])->name('opciones.marcarCorrecta');
    });

    // Respuestas de estudiantes
    Route::post('/actividades/{actividad}/responder', [RespuestaEstudianteController::class, 'store'])->name('respuestas.store');

    // Calificaciones — instructor califica, estudiante ve las suyas
    Route::middleware('instructor')->group(function () {
        Route::get('/calificaciones', [CalificacionController::class, 'index'])->name('calificaciones.index');
        // Ruta estática antes de {respuesta} para evitar conflicto de binding.
        Route::get('/calificaciones/curso/{curso}', [CalificacionController::class, 'curso'])->name('calificaciones.curso');
        Route::get('/calificaciones/{respuesta}', [CalificacionController::class, 'show'])->name('calificaciones.show');
        Route::put('/calificaciones/{respuesta}', [CalificacionController::class, 'update'])->name('calificaciones.update');
        Route::get('/calificaciones/{respuesta}/revisar', [CalificacionController::class, 'revisarCuestionario'])->name('calificaciones.revisar');
        Route::post('/calificaciones/{respuesta}/publicar', [CalificacionController::class, 'publicarCuestionario'])->name('calificaciones.publicar');
    });
    Route::get('/mis-calificaciones', [CalificacionController::class, 'misCalificaciones'])->name('calificaciones.mis');

    // Reportes (admin e instructor)
    Route::middleware('instructor')->group(function () {
        Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('/reportes/cursos/{curso}', [ReporteController::class, 'curso'])->name('reportes.curso');
        Route::get('/reportes/cursos/{curso}/csv', [ReporteController::class, 'exportarCsvCurso'])->name('reportes.csv');
        Route::get('/reportes/cursos/{curso}/pdf', [ReporteController::class, 'exportarPdfCurso'])->name('reportes.pdf');
        Route::get('/reportes/estudiantes/{usuario}', [ReporteController::class, 'estudiante'])->name('reportes.estudiante');
    });

    // Certificados
    Route::get('/certificados', [CertificadoController::class, 'misCertificados'])->name('certificados.mis');
    Route::post('/cursos/{curso}/certificado', [CertificadoController::class, 'generar'])->name('certificados.generar');
    Route::get('/certificados/{certificado}', [CertificadoController::class, 'show'])->name('certificados.show');
    Route::get('/certificados/{certificado}/descargar', [CertificadoController::class, 'descargar'])->name('certificados.descargar');
    Route::get('/certificados/{certificado}/previsualizar', [CertificadoController::class, 'previsualizar'])->name('certificados.previsualizar');

    // Anuncios
    Route::get('/anuncios', [AnuncioController::class, 'todos'])->name('anuncios.todos');
    Route::get('/cursos/{curso}/anuncios', [AnuncioController::class, 'index'])->name('anuncios.index');
    Route::middleware('instructor')->group(function () {
        Route::get('/cursos/{curso}/anuncios/crear', [AnuncioController::class, 'create'])->name('anuncios.create');
        Route::post('/cursos/{curso}/anuncios', [AnuncioController::class, 'store'])->name('anuncios.store');
    });

    // Foros
    Route::get('/cursos/{curso}/foros', [ForoController::class, 'index'])->name('foros.index');
    Route::get('/foros/{foro}', [ForoController::class, 'show'])->name('foros.show');
    Route::post('/foros/{foro}/comentar', [ForoController::class, 'comentar'])->name('foros.comentar');
    Route::middleware('instructor')->group(function () {
        Route::get('/cursos/{curso}/foros/crear', [ForoController::class, 'create'])->name('foros.create');
        Route::post('/cursos/{curso}/foros', [ForoController::class, 'store'])->name('foros.store');
    });

    // Mensajería interna
    Route::get('/mensajes', [MensajeController::class, 'bandeja'])->name('mensajes.bandeja');
    Route::get('/mensajes/redactar', [MensajeController::class, 'create'])->name('mensajes.create');
    Route::post('/mensajes', [MensajeController::class, 'store'])->name('mensajes.store');
    Route::get('/mensajes/{mensaje}', [MensajeController::class, 'conversacion'])->name('mensajes.conversacion');
    Route::get('/api/mensajes/buscar-destinatarios', [MensajeController::class, 'buscarDestinatarios'])->name('mensajes.buscar');

    // Notificaciones
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::get('/notificaciones/{notificacion}/marcar', [NotificacionController::class, 'marcarLeida'])->name('notificaciones.marcar');
    Route::post('/notificaciones/marcar-todas', [NotificacionController::class, 'marcarTodasLeidas'])->name('notificaciones.marcar-todas');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Verificación pública de certificados (sin autenticación)
Route::get('/verificar-certificado/{numero}', [CertificadoController::class, 'verificar'])->name('certificados.verificar');

// Rúbrica (dentro del grupo auth para tener sesión y CSRF correctos)
Route::middleware(['auth', 'instructor'])->group(function () {
    Route::post('/actividades/{actividad}/rubrica', [\App\Http\Controllers\RubricaController::class, 'store'])
        ->name('rubrica.store');
    Route::post('/rubrica/importar/{actividad}', [\App\Http\Controllers\RubricaController::class, 'importar'])
        ->name('rubrica.importar');
    Route::post('/calificaciones/{respuesta}/rubrica', [CalificacionController::class, 'guardarRubrica'])
        ->name('calificaciones.rubrica');
});
Route::middleware('auth')->get('/rubrica/ejemplo', [\App\Http\Controllers\RubricaController::class, 'ejemplo'])
    ->name('rubrica.ejemplo');
Route::middleware('auth')->get('/preguntas/ejemplo', [ImportacionCuestionarioController::class, 'ejemplo'])
    ->name('preguntas.ejemplo');

// 2FA (sin middleware 2fa para evitar redirección circular)
Route::middleware('auth')->group(function () {
    Route::get('/2fa/setup',    [\App\Http\Controllers\Auth\TwoFactorController::class, 'setup'])->name('2fa.setup');
    Route::post('/2fa/enable',  [\App\Http\Controllers\Auth\TwoFactorController::class, 'enable'])->name('2fa.enable');
    Route::post('/2fa/disable', [\App\Http\Controllers\Auth\TwoFactorController::class, 'disable'])->name('2fa.disable');
    Route::get('/2fa/verify',   [\App\Http\Controllers\Auth\TwoFactorController::class, 'verify'])->name('2fa.verify');
    Route::post('/2fa/check',   [\App\Http\Controllers\Auth\TwoFactorController::class, 'check'])->name('2fa.check');
});

// Exportar Excel (instructor/admin)
Route::middleware(['auth', 'instructor'])->group(function () {
    Route::get('/reportes/cursos/{curso}/excel',
        [\App\Http\Controllers\ReporteController::class, 'exportarExcelCurso'])
        ->name('reportes.excel.curso');
});
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/reportes/usuarios/excel',
        [\App\Http\Controllers\ReporteController::class, 'exportarExcelUsuarios'])
        ->name('reportes.excel.usuarios');
});

// Admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class)
        ->except(['show']);
    Route::resource('categorias', \App\Http\Controllers\Admin\CategoriaController::class)
        ->except(['show']);
    Route::get('auditoria', [\App\Http\Controllers\Admin\AuditoriaController::class, 'index'])
        ->name('auditoria.index');
});

require __DIR__.'/auth.php';
