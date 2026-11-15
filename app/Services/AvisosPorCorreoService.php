<?php

namespace App\Services;

/**
 * Qué avisos de la plataforma también salen por correo y bajo qué categoría
 * puede apagarlos cada usuario (Perfil → Avisos por correo).
 */
class AvisosPorCorreoService
{
    /** clave de categoría => etiqueta que ve el usuario */
    public const CATEGORIAS = [
        'vencimientos'   => 'Vencimientos de entregas',
        'calificaciones' => 'Calificaciones',
        'anuncios'       => 'Anuncios',
        'certificados'   => 'Certificados',
    ];

    /** tipo de Notificacion => clave de categoría. Los tipos que no están aquí no salen por correo. */
    public const TIPOS = [
        'vencimiento'   => 'vencimientos',
        'calificacion'  => 'calificaciones',
        'intento_extra' => 'calificaciones',
        'anuncio'       => 'anuncios',
        'certificado'   => 'certificados',
    ];

    public static function categoriaDe(string $tipo): ?string
    {
        return self::TIPOS[$tipo] ?? null;
    }
}
