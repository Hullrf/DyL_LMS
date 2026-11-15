<?php

namespace App\Mail;

use App\Models\Notificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Correo genérico que replica un aviso de la plataforma (título, mensaje y enlace). */
class AvisoLms extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Notificacion $notificacion)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notificacion->titulo);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.aviso',
            with: [
                'titulo'    => $this->notificacion->titulo,
                'mensaje'   => $this->notificacion->mensaje,
                'url'       => $this->notificacion->data['url'] ?? null,
                'perfilUrl' => route('profile.edit'),
            ],
        );
    }
}
