<?php

namespace App\Mail;

use App\Enums\MailGroup;
use App\Filament\Member\Resources\NewsPosts\NewsPostResource;
use App\Models\NewsPost;
use App\Models\User;
use App\Support\MailPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class NewsPostMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public NewsPost $post,
        public User $recipient,
    ) {
        $this->afterCommit();
        $this->post->loadMissing(['team', 'author']);
    }

    public function envelope(): Envelope
    {
        $team = $this->post->team?->name ?? __('News');

        return new Envelope(
            subject: __(':Team: :title', [
                'team' => $team,
                'title' => $this->post->title,
            ]),
        );
    }

    public function headers(): Headers
    {
        $url = URL::signedRoute('mail-preferences.unsubscribe', [
            'user' => $this->recipient,
            'group' => MailGroup::News,
        ]);

        return new Headers(
            text: [
                'List-Unsubscribe' => '<'.$url.'>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.news.post',
            with: [
                'post' => $this->post,
                'recipient' => $this->recipient,
                'url' => NewsPostResource::getUrl('view', [
                    'team' => $this->post->team,
                    'record' => $this->post,
                ], panel: 'member'),
                ...MailPreference::footerViewData($this->recipient, MailGroup::News),
            ],
        );
    }
}
