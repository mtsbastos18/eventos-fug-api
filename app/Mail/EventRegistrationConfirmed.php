<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Participant;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class EventRegistrationConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public $participant;

    /**
     * Create a new message instance.
     */
    public function __construct(Participant $participant)
    {
        $this->participant = $participant;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Inscrição Confirmada no Evento',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // PngWriter usa GD (não Imagick), que é a extensão disponível no ambiente de
        // hospedagem compartilhada — SVG foi descartado por não renderizar na maioria
        // dos clientes de e-mail (Gmail, Outlook etc.).
        $qrCode = Builder::create()
            ->writer(new PngWriter())
            ->data('urn:uuid:' . $this->participant->checkin_token)
            ->size(240)
            ->margin(10)
            ->build();

        // Embutido via cid: (anexo inline), não como data URI: muitos clientes de e-mail
        // (Gmail, Outlook) bloqueiam imagens data:base64 em <img src="">, mesmo válidas.
        return new Content(
            view: 'emails.events.registration_confirmed',
            with: [
                'qrCodePng' => $qrCode->getString(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
