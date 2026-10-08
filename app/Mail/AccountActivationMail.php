<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Mail mit dem Aktivierungslink (Sie-Form). Der Klartext-Token steht nur in dieser Mail, in der Datenbank liegt nur sein Hash. */
class AccountActivationMail extends Mailable
{
    public function __construct(public string $firstName, public string $token, public int $validHours) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Ihr Zugang zu Vectory'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-activation', with: [
            'firstName' => $this->firstName,
            'url' => route('activation.form', $this->token),
            'validHours' => $this->validHours,
        ]);
    }
}
