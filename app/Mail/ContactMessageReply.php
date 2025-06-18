<?php

// app/Mail/ContactMessageReply.php
namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageReply extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ContactMessage $contactMessage;

    public function __construct(ContactMessage $contactMessage)
    {
        $this->contactMessage = $contactMessage;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Re: {$this->contactMessage->subject}",
            replyTo: $this->contactMessage->branch->email ?? config('mail.from.address'),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.contact-message-reply',
            text: 'emails.contact-message-reply-text',
            with: [
                'message' => $this->contactMessage,
                'customerName' => $this->contactMessage->name,
                'branchName' => $this->contactMessage->branch_name,
                'originalSubject' => $this->contactMessage->subject,
                'originalMessage' => $this->contactMessage->message,
                'adminResponse' => $this->contactMessage->admin_response,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}