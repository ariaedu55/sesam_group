<?php

declare(strict_types=1);

namespace Sesam\Core\Mail;

/**
 * Client SMTP minimal (sans bibliothèque) : SSL direct (465) ou STARTTLS (587), AUTH LOGIN, un message à la fois.
 * Les certificats sont vérifiés avec le paquet de certificats racine du projet (storage/certs/cacert.pem).
 */
final class SmtpClient
{
    /** @var resource|null */
    private $socket = null;

    /**
     * @param array{host: string, port: int, encryption: string, username: string, password: string, timeout: int, ca_bundle: string} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Connexion, présentation et authentification. Lève MailException avec un message clair en cas d'échec.
     */
    public function connect(): void
    {
        $host = trim($this->config['host']);
        if ($host === '' || $this->config['username'] === '' || $this->config['password'] === '') {
            throw new MailException('SMTP incomplet : renseignez SMTP_HOST, SMTP_USER et SMTP_PASSWORD dans .env.');
        }
        $ssl = $this->config['encryption'] === 'ssl';
        $context = stream_context_create(['ssl' => array_filter([
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'cafile' => is_file($this->config['ca_bundle']) ? $this->config['ca_bundle'] : null,
        ], static fn ($value): bool => $value !== null)]);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            ($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $this->config['port'],
            $errno,
            $errstr,
            $this->config['timeout'],
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if ($socket === false) {
            throw new MailException('Connexion SMTP impossible à ' . $host . ':' . $this->config['port'] . ' (' . trim($errstr !== '' ? $errstr : 'erreur ' . $errno) . ').');
        }
        stream_set_timeout($socket, $this->config['timeout']);
        $this->socket = $socket;

        $this->expect(220);
        $this->hello();
        if (!$ssl && $this->config['encryption'] === 'tls') {
            $this->command('STARTTLS', 220);
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new MailException('Le chiffrement STARTTLS a échoué.');
            }
            $this->hello();
        }
        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($this->config['username']), 334, 'utilisateur');
        try {
            $this->command(base64_encode($this->config['password']), 235, 'mot de passe');
        } catch (MailException $exception) {
            throw new MailException('Authentification SMTP refusée : vérifiez SMTP_USER et SMTP_PASSWORD. (' . $exception->getMessage() . ')');
        }
    }

    /**
     * @param list<string> $recipients
     */
    public function send(string $from, array $recipients, string $data): void
    {
        $this->command('MAIL FROM:<' . $from . '>', 250);
        foreach ($recipients as $recipient) {
            $this->command('RCPT TO:<' . $recipient . '>', [250, 251]);
        }
        $this->command('DATA', 354);
        // Les lignes qui commencent par un point sont doublées (fin de message = ligne « . » seule).
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $data)) ?? $data;
        $this->write(str_replace("\n", "\r\n", rtrim($data, "\n")) . "\r\n.\r\n");
        $this->expect(250);
    }

    public function quit(): void
    {
        if ($this->socket === null) {
            return;
        }
        try {
            $this->write("QUIT\r\n");
        } catch (MailException) {
            // Déjà fermé.
        }
        fclose($this->socket);
        $this->socket = null;
    }

    public function __destruct()
    {
        $this->quit();
    }

    /**
     * Présentation avec le domaine de l'expéditeur (ex. goo-bridge.com) plutôt que le nom de la machine
     * (« DESKTOP-… ») : un nom d'hôte local dans les en-têtes « Received » pénalise la délivrabilité.
     */
    private function hello(): void
    {
        $domain = substr((string) strrchr($this->config['username'], '@'), 1);
        if ($domain === '' || !str_contains($domain, '.')) {
            $domain = $this->config['host'];
        }
        $this->command('EHLO ' . (preg_replace('/[^A-Za-z0-9.-]/', '', $domain) ?: 'localhost'), 250);
    }

    /**
     * @param int|list<int> $expected
     */
    private function command(string $line, int|array $expected, ?string $hidden = null): string
    {
        $this->write($line . "\r\n");

        return $this->expect($expected, $hidden);
    }

    /**
     * @param int|list<int> $expected
     */
    private function expect(int|array $expected, ?string $hidden = null): string
    {
        $response = '';
        while (true) {
            $line = $this->socket !== null ? fgets($this->socket, 1024) : false;
            if ($line === false) {
                throw new MailException('Le serveur SMTP a fermé la connexion ou ne répond plus.');
            }
            $response .= $line;
            // Réponse sur plusieurs lignes : « 250-… » puis « 250 … ».
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, (array) $expected, true)) {
            $detail = trim(preg_replace('/\s+/', ' ', $response) ?? $response);
            throw new MailException('Réponse SMTP inattendue' . ($hidden !== null ? ' (' . $hidden . ')' : '') . ' : ' . mb_substr($detail, 0, 200));
        }

        return $response;
    }

    private function write(string $data): void
    {
        if ($this->socket === null || @fwrite($this->socket, $data) === false) {
            throw new MailException('Écriture impossible vers le serveur SMTP.');
        }
    }
}
