<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesControlTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioConRol(string $nombreRol): User
    {
        $rol = Rol::factory()->create(['nombre' => $nombreRol]);
        $user = User::factory()->create(['estado' => 'activo']);
        $user->roles()->attach($rol);
        return $user;
    }

    // --- Rutas de solo instructor ---

    public function test_estudiante_no_puede_ver_calificaciones_de_instructor(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');
        $response = $this->actingAs($student)->get('/calificaciones');
        $response->assertStatus(403);
    }

    public function test_instructor_puede_ver_calificaciones(): void
    {
        $instructor = $this->crearUsuarioConRol('Instructor');
        $response = $this->actingAs($instructor)->get('/calificaciones');
        $response->assertStatus(200);
    }

    public function test_estudiante_no_puede_ver_reportes(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');
        $response = $this->actingAs($student)->get('/reportes');
        $response->assertStatus(403);
    }

    public function test_instructor_puede_ver_reportes(): void
    {
        $instructor = $this->crearUsuarioConRol('Instructor');
        $response = $this->actingAs($instructor)->get('/reportes');
        $response->assertStatus(200);
    }

    public function test_estudiante_no_puede_crear_curso(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');
        $response = $this->actingAs($student)->get('/cursos/crear');
        $response->assertStatus(403);
    }

    public function test_instructor_puede_crear_curso(): void
    {
        $instructor = $this->crearUsuarioConRol('Instructor');
        $response = $this->actingAs($instructor)->get('/cursos/crear');
        $response->assertStatus(200);
    }

    // --- Acceso público ---

    public function test_pagina_verificar_certificado_es_publica(): void
    {
        $response = $this->get('/verificar-certificado/CERT-2026-XXXXXXXX');
        $response->assertStatus(200);
    }

    // --- Dashboard por rol ---

    public function test_admin_dashboard_carga(): void
    {
        $admin = $this->crearUsuarioConRol('Administrador');
        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_instructor_dashboard_carga(): void
    {
        $instructor = $this->crearUsuarioConRol('Instructor');
        $response = $this->actingAs($instructor)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_estudiante_dashboard_carga(): void
    {
        $student = $this->crearUsuarioConRol('Estudiante');
        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertStatus(200);
    }
}
