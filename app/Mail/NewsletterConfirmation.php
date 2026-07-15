<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly NewsletterSubscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your subscription');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.newsletter-confirmation',
            with: [
                'confirmUrl' => route('newsletter.confirm', $this->subscriber->confirmation_token),
                'unsubscribeUrl' => route('newsletter.unsubscribe', $this->subscriber->confirmation_token),
            ],
        );
    }
}
