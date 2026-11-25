<?php

namespace Codeart\Core\Auth\Mail;

use Codeart\Core\Theme\ThemeSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Base of the system mails: one template (codeart::mail.message), texts from the `codeart::mail` language
 * files in the mail's locale, colors from ThemeSettings (never literal values in the template).
 */
abstract class ThemedMail extends Mailable
{
    /** Translation key under `codeart::mail.` (subject, heading, lines, action). */
    abstract protected function key(): string;

    /** @return array<string, string> placeholders of the texts */
    protected function replacements(): array
    {
        return [];
    }

    /** @return list<string> keys of the text paragraphs, relative to key() */
    protected function paragraphs(): array
    {
        return ['intro'];
    }

    protected function actionUrl(): ?string
    {
        return null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->line('subject'));
    }

    public function content(): Content
    {
        $url = $this->actionUrl();

        return new Content(
            view: 'codeart::mail.message', // @phpstan-ignore argument.type (package view namespace is unknown to phpstan)
            with: [
                'palette' => app(ThemeSettings::class)->emailPalette(),
                'appName' => (string) config('app.name'),
                'heading' => $this->line('heading'),
                'paragraphs' => array_map(fn (string $p): string => $this->line($p), $this->paragraphs()),
                'actionText' => $url === null ? null : $this->line('action'),
                'actionUrl' => $url,
                'footer' => $url === null ? null : __('codeart::mail.button_help'),
            ],
        );
    }

    /** @param  array<string, string>  $extra */
    protected function line(string $name, array $extra = []): string
    {
        return __('codeart::mail.'.$this->key().'.'.$name, [...$this->replacements(), ...$extra, 'app' => (string) config('app.name')]);
    }
}
