<?php

declare(strict_types=1);

namespace Sesam\Core\Mail;

use Sesam\Core\Logger\Logger;

/**
 * Envoi des e-mails de SESAM. Pilote « smtp » (réel), « log » (écrit dans storage/logs/mail.log, pour le
 * développement et les tests) ou « off ». Chaque e-mail part en texte + HTML aux couleurs de SESAM.
 */
final class Mailer
{
    private static ?self $current = null;

    /** @var list<array{to: string, subject: string, text: string}> E-mails « envoyés » par le pilote log (tests). */
    private array $outbox = [];

    /**
     * @param array{driver: string, host: string, port: int, encryption: string, username: string, password: string, from: string, from_name: string, reply_to: string, timeout: int, ca_bundle: string, log_file: string} $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ?Logger $logger = null,
    ) {
    }

    public static function setCurrent(?self $mailer): void
    {
        self::$current = $mailer;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public function driver(): string
    {
        return $this->config['driver'];
    }

    public function isLive(): bool
    {
        return $this->config['driver'] === 'smtp';
    }

    /**
     * E-mail aux couleurs de SESAM : un titre, des paragraphes, un bouton facultatif.
     *
     * @param list<string> $paragraphs
     */
    public function sendTemplate(string $to, string $subject, string $title, array $paragraphs, ?string $buttonLabel = null, ?string $buttonUrl = null, string $footer = ''): void
    {
        $text = $title . "\n\n" . implode("\n\n", $paragraphs)
            . ($buttonUrl !== null ? "\n\n" . ($buttonLabel ?? 'Ouvrir') . ' : ' . $buttonUrl : '')
            . ($footer !== '' ? "\n\n" . $footer : '') . "\n\n— L'équipe SESAM";
        $this->send($to, $subject, $text, $this->html($title, $paragraphs, $buttonLabel, $buttonUrl, $footer));
    }

    /**
     * Envoi direct. Lève MailException si le serveur refuse (l'appelant décide quoi faire).
     */
    public function send(string $to, string $subject, string $text, ?string $html = null): void
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new MailException('Adresse e-mail invalide : ' . $to);
        }
        if ($this->config['driver'] === 'off') {
            return;
        }
        if ($this->config['driver'] === 'log' || self::isReservedAddress($to)) {
            $this->outbox[] = ['to' => $to, 'subject' => $subject, 'text' => $text];
            @file_put_contents($this->config['log_file'], '[' . gmdate('c') . '] À : ' . $to . ' — ' . $subject . "\n" . $text . "\n\n", FILE_APPEND);

            return;
        }
        $from = trim($this->config['from']) !== '' ? trim($this->config['from']) : trim($this->config['username']);
        $client = new SmtpClient($this->config);
        try {
            $client->connect();
            $client->send($from, [$to], $this->message($from, $to, $subject, $text, $html));
            $this->logger?->info('E-mail envoyé à ' . $to . ' — ' . $subject);
        } catch (MailException $exception) {
            $this->logger?->error('E-mail non envoyé à ' . $to . ' : ' . $exception->getMessage());
            throw $exception;
        } finally {
            $client->quit();
        }
    }

    /**
     * Domaines réservés aux exemples et aux tests (RFC 2606) : jamais d'envoi réel, même en SMTP
     * (données de démonstration et de test, qui partagent la base de développement).
     */
    public static function isReservedAddress(string $email): bool
    {
        $domain = strtolower(substr((string) strrchr($email, '@'), 1));

        return in_array($domain, ['example.com', 'example.org', 'example.net'], true)
            || preg_match('/\.(test|example|invalid|localhost|local)$/', $domain) === 1;
    }

    /**
     * Vérifie la connexion et l'authentification sans rien envoyer (script de test).
     */
    public function checkConnection(): void
    {
        $client = new SmtpClient($this->config);
        try {
            $client->connect();
        } finally {
            $client->quit();
        }
    }

    /**
     * @return list<array{to: string, subject: string, text: string}>
     */
    public function outbox(): array
    {
        return $this->outbox;
    }

    private function message(string $from, string $to, string $subject, string $text, ?string $html): string
    {
        $domain = substr((string) strrchr($from, '@'), 1) ?: 'sesam.local';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $this->encode($this->config['from_name']) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $this->encode($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
        ];
        if (trim($this->config['reply_to']) !== '') {
            $headers[] = 'Reply-To: <' . trim($this->config['reply_to']) . '>';
        }
        $textPart = "Content-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: base64\n\n" . chunk_split(base64_encode($text), 76, "\n");
        if ($html === null) {
            return implode("\n", $headers) . "\n" . $textPart;
        }
        $boundary = 'sesam-' . bin2hex(random_bytes(8));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        return implode("\n", $headers) . "\n\n"
            . '--' . $boundary . "\n" . $textPart . "\n"
            . '--' . $boundary . "\nContent-Type: text/html; charset=UTF-8\nContent-Transfer-Encoding: base64\n\n" . chunk_split(base64_encode($html), 76, "\n") . "\n"
            . '--' . $boundary . "--\n";
    }

    private function encode(string $value): string
    {
        return preg_match('/[^\x20-\x7e]/', $value) === 1 ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    /**
     * @param list<string> $paragraphs
     */
    private function html(string $title, array $paragraphs, ?string $buttonLabel, ?string $buttonUrl, string $footer): string
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = '';
        foreach ($paragraphs as $paragraph) {
            $body .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1d2433">' . nl2br($e($paragraph)) . '</p>';
        }
        if ($buttonUrl !== null) {
            $body .= '<p style="margin:22px 0"><a href="' . $e($buttonUrl) . '" style="display:inline-block;padding:13px 24px;border-radius:999px;background:#0a6cf0;color:#ffffff;font-weight:700;text-decoration:none">' . $e($buttonLabel ?? 'Ouvrir') . '</a></p>'
                . '<p style="margin:0 0 14px;font-size:12px;line-height:1.5;color:#6b7385">Si le bouton ne fonctionne pas, copiez ce lien : <br><span style="word-break:break-all">' . $e($buttonUrl) . '</span></p>';
        }
        if ($footer !== '') {
            $body .= '<p style="margin:18px 0 0;font-size:13px;line-height:1.5;color:#6b7385">' . nl2br($e($footer)) . '</p>';
        }

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . $e($title) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f3f5f9;font-family:Segoe UI,Helvetica,Arial,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f9;padding:28px 12px"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden">'
            . '<tr><td style="background:#071a3d;padding:20px 28px;color:#ffffff;font-size:20px;font-weight:800;letter-spacing:.04em">SESAM</td></tr>'
            . '<tr><td style="padding:28px">'
            . '<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#071a3d">' . $e($title) . '</h1>'
            . $body
            . '</td></tr>'
            . '<tr><td style="padding:16px 28px;background:#f7f8fb;color:#8a92a3;font-size:12px">L\'équipe SESAM · Ce message a été envoyé automatiquement.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
