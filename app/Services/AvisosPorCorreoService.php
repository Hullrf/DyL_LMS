<?php

namespace App\Services;

use App\Mail\AvisoLms;
use App\Models\Notificacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    /**
     * Encola el correo de un aviso recién creado si corresponde: el tipo tiene
     * categoría, el usuario existe, está activo, tiene correo y no apagó la
     * categoría. Nunca lanza: un fallo del correo no debe romper la acción que
     * originó el aviso (calificar, publicar un anuncio…).
     *
     * @return bool true si se encoló un correo
     */
    public function enviarSiCorresponde(Notificacion $aviso): bool
    {
        try {
            $categoria = self::categoriaDe($aviso->tipo);
            if ($categoria === null) {
                return false;
            }

            $usuario = $aviso->user;
            if (!$usuario || $usuario->estado !== 'activo' || !$usuario->email || !$usuario->recibeCorreo($categoria)) {
                return false;
            }

            Mail::to($usuario->email)->queue(new AvisoLms($aviso));

            return true;
        } catch (\Throwable $e) {
            Log::warning('No se pudo encolar el aviso por correo', [
                'notificacion_id' => $aviso->id,
                'error'           => $e->getMessage(),
            ]);

            return false;
        }
    }
}
