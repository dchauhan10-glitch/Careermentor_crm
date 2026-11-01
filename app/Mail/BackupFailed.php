<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BackupFailed extends Mailable
{
    public function __construct(public string $what, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "CRM {$this->what} failed");
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>The CareerMentors CRM '.e($this->what).' <b>failed</b> at '.now()->format('j M Y, g:i A').'.</p>'
            .'<p>Reason: '.e($this->reason).'</p><p>Please check Backups in the CRM.</p>');
    }
}
