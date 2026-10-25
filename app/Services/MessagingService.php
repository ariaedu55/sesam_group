<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Messaging\DeliveryStatus;
use Sesam\Core\Messaging\MessageChannel;
use Sesam\Core\Messaging\TemplateRenderer;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\MessageDelivery;
use Sesam\Models\MessageTemplate;
use Sesam\Models\User;
use Sesam\Repositories\MessageDeliveryRepository;
use Sesam\Repositories\MessageReminderRepository;
use Sesam\Repositories\MessageTemplateRepository;
use Sesam\Repositories\NotificationRepository;

final class MessagingService extends Service
{
    public const PER_PAGE = 25;

    private readonly MessageTemplateRepository $templates;
    private readonly MessageDeliveryRepository $deliveries;
    private readonly MessageReminderRepository $reminders;
    private readonly NotificationRepository $inbox;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     * @param array{
     *     mode: string,
     *     mail: array{from: string, smtp_host: string, smtp_port: string, smtp_user: string, smtp_password: string},
     *     whatsapp: array{api_url: string, api_token: string}
     * } $config
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly array $config,
        private readonly ?Logger $logger = null,
    ) {
        $pdo = Connection::make($database);
        $this->templates = new MessageTemplateRepository($pdo);
        $this->deliveries = new MessageDeliveryRepository($pdo);
        $this->reminders = new MessageReminderRepository($pdo);
        $this->inbox = new NotificationRepository($pdo);
    }

    /**
     * @return list<MessageTemplate>
     */
    public function templates(User $actor): array
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::NOTIFICATIONS_SEND);

        return $this->templates->listForTenant($tenantId);
    }

    /**
     * @return array{deliveries: list<MessageDelivery>, total: int, page: int, pages: int}
     */
    public function history(User $actor, int $page): array
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::NOTIFICATIONS_HISTORY);
        $page = max(1, $page);
        $total = $this->deliveries->countForTenant($tenantId);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        return [
            'deliveries' => $this->deliveries->listForTenant($tenantId, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function send(User $actor, array $input): MessageDelivery
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::NOTIFICATIONS_SEND);
        $channel = trim((string) ($input['channel'] ?? ''));
        $recipient = trim((string) ($input['recipient'] ?? ''));
        $templateSlug = trim((string) ($input['template_slug'] ?? ''));
        $name = trim((string) ($input['name'] ?? $actor->fullName()));
        $tenant = $this->context->tenant();
        $commerce = $tenant !== null ? $tenant->displayName() : 'Commerce';

        if (!in_array($channel, MessageChannel::all(), true)) {
            throw new ValidationException(['channel' => 'Canal invalide.']);
        }
        if ($recipient === '') {
            throw new ValidationException(['recipient' => 'Indiquez un destinataire.']);
        }
        if ($templateSlug === '') {
            throw new ValidationException(['template_slug' => 'Choisissez un modèle.']);
        }

        $template = $this->templates->findForTenant($tenantId, $templateSlug, $channel);
        if ($template === null || $template->channel() !== $channel) {
            throw new ValidationException(['template_slug' => 'Modèle introuvable pour ce canal.']);
        }

        /** @var array<string, string> $variables */
        $variables = [
            'name' => $name !== '' ? $name : 'Client',
            'commerce' => $commerce,
            'amount' => trim((string) ($input['amount'] ?? '0')),
            'client' => trim((string) ($input['client'] ?? $name)),
        ];
        $body = TemplateRenderer::render($template->body(), $variables);
        $subject = $template->subject() !== null
            ? TemplateRenderer::render($template->subject(), $variables)
            : null;

        $result = $this->dispatch($tenantId, $channel, $recipient, $templateSlug, $subject, $body);
        $deliveryId = $this->deliveries->insert([
            'tenant_id' => $tenantId,
            'channel' => $channel,
            'recipient' => $recipient,
            'template_slug' => $templateSlug,
            'subject' => $subject,
            'body' => $body,
            'status' => $result['status'],
            'error_message' => $result['error'],
            'attempts' => 1,
            'sent_at' => $result['sent_at'],
        ]);

        if ($channel === MessageChannel::INTERNAL && $result['status'] === DeliveryStatus::SENT) {
            $title = $subject ?? $template->name();
            $this->inbox->insert($tenantId, null, $title, $body);
        }

        $delivery = $this->deliveries->findById($deliveryId, $tenantId);
        if ($delivery === null) {
            throw new HttpException(500, 'Une erreur est survenue.');
        }

        return $delivery;
    }

    /**
     * @return array{status: string, mock: bool}
     */
    public function notifyPublicWhatsApp(string $recipient, string $name, string $body): array
    {
        $tenantId = $this->context->requireId();
        $phone = trim($recipient);
        if ($phone === '') {
            return ['status' => DeliveryStatus::FAILED, 'mock' => $this->isMockMode()];
        }
        $text = trim($body);
        if ($text === '') {
            $text = 'Confirmation ' . ($this->context->tenant()?->displayName() ?? 'SESAM');
        }
        $result = $this->dispatch(
            $tenantId,
            MessageChannel::WHATSAPP,
            $phone,
            'portal_confirm',
            null,
            $text,
        );
        $this->deliveries->insert([
            'tenant_id' => $tenantId,
            'channel' => MessageChannel::WHATSAPP,
            'recipient' => $phone,
            'template_slug' => 'portal_confirm',
            'subject' => null,
            'body' => $text,
            'status' => $result['status'],
            'error_message' => $result['error'],
            'attempts' => 1,
            'sent_at' => $result['sent_at'],
        ]);
        unset($name);

        return [
            'status' => $result['status'],
            'mock' => $result['status'] === DeliveryStatus::MOCK,
        ];
    }

    /**
     * Message à un client : WhatsApp d'abord ; si WhatsApp ne l'a pas réellement remis (pas encore branché, échec
     * ou pas de numéro) et que le client a une adresse e-mail, le même message part par e-mail.
     *
     * @return array{status: string, mock: bool, channel: string}
     */
    public function notifyClient(string $phone, ?string $email, string $name, string $subject, string $body): array
    {
        $result = ['status' => DeliveryStatus::FAILED, 'mock' => $this->isMockMode()];
        if (trim($phone) !== '') {
            $result = $this->notifyPublicWhatsApp($phone, $name, $body);
            if ($result['status'] === DeliveryStatus::SENT) {
                return $result + ['channel' => MessageChannel::WHATSAPP];
            }
        }
        $email = trim((string) $email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$this->emailFallbackAvailable()) {
            return $result + ['channel' => MessageChannel::WHATSAPP];
        }
        $tenantId = $this->context->requireId();
        $sent = $this->dispatch($tenantId, MessageChannel::EMAIL, $email, 'portal_confirm', $subject, ($name !== '' ? 'Bonjour ' . $name . ",\n\n" : '') . trim($body));
        $this->deliveries->insert([
            'tenant_id' => $tenantId,
            'channel' => MessageChannel::EMAIL,
            'recipient' => $email,
            'template_slug' => 'portal_confirm',
            'subject' => $subject,
            'body' => trim($body),
            'status' => $sent['status'],
            'error_message' => $sent['error'],
            'attempts' => 1,
            'sent_at' => $sent['sent_at'],
        ]);

        return ['status' => $sent['status'], 'mock' => $sent['status'] === DeliveryStatus::MOCK, 'channel' => MessageChannel::EMAIL];
    }

    /** L'e-mail peut prendre le relais (SMTP réel, ou journal en développement et pendant les tests). */
    private function emailFallbackAvailable(): bool
    {
        $mailer = \Sesam\Core\Mail\Mailer::current();

        return $mailer !== null && $mailer->driver() !== 'off';
    }

    /**
     * @param array<string, mixed> $input
     */
    public function scheduleReminder(User $actor, array $input): int
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::NOTIFICATIONS_SEND);
        $channel = trim((string) ($input['channel'] ?? MessageChannel::INTERNAL));
        $recipient = trim((string) ($input['recipient'] ?? ''));
        $templateSlug = trim((string) ($input['template_slug'] ?? 'test_message'));
        $sendAt = trim((string) ($input['send_at'] ?? ''));
        if ($recipient === '' || $sendAt === '') {
            throw new ValidationException(['send_at' => 'Destinataire et date d\'envoi requis.']);
        }
        $timestamp = strtotime($sendAt);
        if ($timestamp === false) {
            throw new ValidationException(['send_at' => 'Date invalide.']);
        }

        $variables = [
            'name' => trim((string) ($input['name'] ?? 'Client')),
            'commerce' => $this->context->tenant()?->displayName() ?? 'Commerce',
            'amount' => trim((string) ($input['amount'] ?? '0')),
            'client' => trim((string) ($input['client'] ?? '')),
            'details' => trim((string) ($input['details'] ?? '')),
        ];

        return $this->reminders->insert(
            $tenantId,
            $channel,
            $recipient,
            $templateSlug,
            json_encode($variables, JSON_THROW_ON_ERROR),
            gmdate('Y-m-d H:i:s', $timestamp),
        );
    }

    /**
     * Rappel automatique (pas une action d'un utilisateur) : aucun droit d'envoi n'est exigé, le rendez-vous
     * ou l'échéance a déjà été validé. $reference rattache le rappel à son objet pour pouvoir l'annuler.
     *
     * @param array<string, string> $variables
     */
    public function scheduleAutomatic(int $tenantId, string $channel, string $recipient, string $templateSlug, array $variables, string $sendAt, ?string $reference = null): int
    {
        if (trim($recipient) === '') {
            return 0;
        }
        if ($reference !== null) {
            $this->reminders->cancelPending($tenantId, $reference);
        }
        $variables += ['commerce' => $this->context->tenant()?->displayName() ?? 'Commerce'];

        return $this->reminders->insert($tenantId, $channel, trim($recipient), $templateSlug, json_encode($variables, JSON_THROW_ON_ERROR), $sendAt, $reference);
    }

    public function cancelReminders(int $tenantId, string $reference): int
    {
        return $this->reminders->cancelPending($tenantId, $reference);
    }

    public function processDueReminders(): int
    {
        $processed = 0;
        foreach ($this->reminders->duePending() as $row) {
            if (!$this->reminders->claim($row['id'])) {
                continue;
            }
            /** @var array<string, string>|null $variables */
            $variables = json_decode($row['variables_json'], true);
            if (!is_array($variables)) {
                $this->reminders->markProcessed($row['id'], 'failed', 'Variables JSON invalides.');
                continue;
            }
            $template = $this->templates->findForTenant($row['tenant_id'], $row['template_slug'], $row['channel']);
            if ($template === null) {
                $this->reminders->markProcessed($row['id'], 'failed', 'Modèle introuvable.');
                continue;
            }
            $body = TemplateRenderer::render($template->body(), $variables);
            $subject = $template->subject() !== null
                ? TemplateRenderer::render($template->subject(), $variables)
                : null;
            $result = $this->dispatch(
                $row['tenant_id'],
                $row['channel'],
                $row['recipient'],
                $row['template_slug'],
                $subject,
                $body,
            );
            $this->deliveries->insert([
                'tenant_id' => $row['tenant_id'],
                'channel' => $row['channel'],
                'recipient' => $row['recipient'],
                'template_slug' => $row['template_slug'],
                'subject' => $subject,
                'body' => $body,
                'status' => $result['status'],
                'error_message' => $result['error'],
                'attempts' => 1,
                'sent_at' => $result['sent_at'],
            ]);
            if ($row['channel'] === MessageChannel::INTERNAL && $result['status'] === DeliveryStatus::SENT) {
                $this->inbox->insert($row['tenant_id'], null, $subject ?? $template->name(), $body);
            }
            $fallback = trim((string) ($variables['email'] ?? ''));
            if ($row['channel'] === MessageChannel::WHATSAPP && $result['status'] !== DeliveryStatus::SENT
                && $fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL) && $this->emailFallbackAvailable()) {
                $emailTemplate = $this->templates->findForTenant($row['tenant_id'], $row['template_slug'], MessageChannel::EMAIL);
                $emailBody = $emailTemplate !== null ? TemplateRenderer::render($emailTemplate->body(), $variables) : $body;
                $emailSubject = $emailTemplate?->subject() !== null ? TemplateRenderer::render((string) $emailTemplate->subject(), $variables) : ($subject ?? 'Rappel');
                $mail = $this->dispatch($row['tenant_id'], MessageChannel::EMAIL, $fallback, $row['template_slug'], $emailSubject, $emailBody);
                $this->deliveries->insert([
                    'tenant_id' => $row['tenant_id'],
                    'channel' => MessageChannel::EMAIL,
                    'recipient' => $fallback,
                    'template_slug' => $row['template_slug'],
                    'subject' => $emailSubject,
                    'body' => $emailBody,
                    'status' => $mail['status'],
                    'error_message' => $mail['error'],
                    'attempts' => 1,
                    'sent_at' => $mail['sent_at'],
                ]);
                if ($mail['status'] !== DeliveryStatus::FAILED) {
                    $result = $mail;
                }
            }
            $this->reminders->markProcessed(
                $row['id'],
                $result['status'] === DeliveryStatus::FAILED ? 'failed' : 'processed',
                $result['error'],
            );
            ++$processed;
        }

        return $processed;
    }

    public function isMockMode(): bool
    {
        return $this->config['mode'] === 'mock';
    }

    /**
     * @return array{status: string, error: ?string, sent_at: ?string}
     */
    private function dispatch(
        int $tenantId,
        string $channel,
        string $recipient,
        string $templateSlug,
        ?string $subject,
        string $body,
    ): array {
        unset($tenantId, $templateSlug);

        return match ($channel) {
            MessageChannel::INTERNAL => [
                'status' => DeliveryStatus::SENT,
                'error' => null,
                'sent_at' => gmdate('Y-m-d H:i:s'),
            ],
            MessageChannel::EMAIL => $this->sendEmail($recipient, $subject ?? 'SESAM', $body),
            MessageChannel::WHATSAPP => $this->sendWhatsApp($recipient, $body),
            default => [
                'status' => DeliveryStatus::FAILED,
                'error' => 'Canal inconnu.',
                'sent_at' => null,
            ],
        };
    }

    /**
     * @return array{status: string, error: ?string, sent_at: ?string}
     */
    private function sendEmail(string $to, string $subject, string $body): array
    {
        // SMTP configuré (MAIL_DRIVER=smtp) : les e-mails partent pour de vrai, même si WhatsApp reste simulé.
        $mailer = \Sesam\Core\Mail\Mailer::current();
        if ($mailer !== null && $mailer->driver() !== 'off') {
            try {
                $commerce = $this->context->tenant()?->displayName() ?? 'SESAM';
                $mailer->sendTemplate($to, $subject, $subject, preg_split('/\R{2,}/', trim($body)) ?: [$body], null, null, 'Message envoyé par ' . $commerce . ' via SESAM.');

                // Pilote « log » (développement, tests) : rien n'est parti réellement.
                return ['status' => $mailer->isLive() ? DeliveryStatus::SENT : DeliveryStatus::MOCK, 'error' => null, 'sent_at' => $mailer->isLive() ? gmdate('Y-m-d H:i:s') : null];
            } catch (\Throwable $exception) {
                return ['status' => DeliveryStatus::FAILED, 'error' => mb_substr($exception->getMessage(), 0, 250), 'sent_at' => null];
            }
        }
        if ($this->isMockMode()) {
            $this->logger?->info('Mock e-mail vers ' . $to . ' — sujet : ' . $subject);

            return [
                'status' => DeliveryStatus::MOCK,
                'error' => 'Mode mock : e-mail non expédié (voir journal).',
                'sent_at' => null,
            ];
        }

        $mail = $this->config['mail'];
        if ($mail['smtp_host'] === '' || $mail['from'] === '') {
            $this->logger?->info('E-mail refusé : SMTP ou MAIL_FROM non configuré.');

            return [
                'status' => DeliveryStatus::FAILED,
                'error' => 'E-mail non configuré (SMTP_HOST, MAIL_FROM).',
                'sent_at' => null,
            ];
        }

        $this->logger?->info('E-mail live demandé mais transport SMTP non branché pour ' . $to);

        return [
            'status' => DeliveryStatus::FAILED,
            'error' => 'Envoi SMTP live non branché dans cette version.',
            'sent_at' => null,
        ];
    }

    /**
     * @return array{status: string, error: ?string, sent_at: ?string}
     */
    private function sendWhatsApp(string $to, string $body): array
    {
        if ($this->isMockMode()) {
            $this->logger?->info('Mock WhatsApp vers ' . $to . ' — ' . mb_substr($body, 0, 80));

            return [
                'status' => DeliveryStatus::MOCK,
                'error' => 'Mode mock : WhatsApp non expédié (voir journal).',
                'sent_at' => null,
            ];
        }

        $whatsapp = $this->config['whatsapp'];
        if ($whatsapp['api_url'] === '' || $whatsapp['api_token'] === '') {
            $this->logger?->info('WhatsApp refusé : WHATSAPP_API_URL ou WHATSAPP_API_TOKEN manquant.');

            return [
                'status' => DeliveryStatus::FAILED,
                'error' => 'WhatsApp non configuré.',
                'sent_at' => null,
            ];
        }

        $this->logger?->info('WhatsApp live demandé mais API non branchée pour ' . $to);

        return [
            'status' => DeliveryStatus::FAILED,
            'error' => 'API WhatsApp live non branchée dans cette version.',
            'sent_at' => null,
        ];
    }

    private function requireTenant(User $actor): int
    {
        if ($actor->tenantId() === null) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $tenantId = $this->context->requireId();
        if ($tenantId !== $actor->tenantId()) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }

        return $tenantId;
    }

    private function assertCan(User $actor, string $permission): void
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
    }
}
