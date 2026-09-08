<?php

namespace App\Mail;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "Projektanfrage" - Vietto-Vorbild: wer kein project.create-Recht hat,
 * schickt stattdessen eine formlose Anfrage an die TR statt selbst ein
 * Projekt anzulegen (Ralf: "Personen, die nicht hauptsächlich mit dem Tool
 * arbeiten, gehen schludrig mit dem Anlegen um"). Kein Projekt-Datensatz
 * entsteht dabei - nur diese Mail.
 */
class ProjectRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Person $requestedBy,
        public readonly string $title,
        public readonly ?string $modelOrSystem,
        public readonly ?string $dueDate,
        public readonly ?string $remarks,
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('Vectory: Neue Projektanfrage – :title', ['title' => $this->title]))
            ->view('emails.project-request')
            ->with([
                'requestedBy' => $this->requestedBy,
                'title' => $this->title,
                'modelOrSystem' => $this->modelOrSystem,
                'dueDate' => $this->dueDate,
                'remarks' => $this->remarks,
            ]);
    }
}
