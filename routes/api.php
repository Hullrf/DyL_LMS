<?php

use App\Http\Controllers\Api\CursoApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/cursos', [CursoApiController::class, 'index']);
    Route::get('/cursos/{curso}', [CursoApiController::class, 'show']);
    Route::get('/mis-cursos', [CursoApiController::class, 'misCursos']);
    Route::get('/mis-actividades', [CursoApiController::class, 'misActividades']);
    Route::post('/lecciones/{leccion}/completar', [CursoApiController::class, 'completarLeccion']);
});
