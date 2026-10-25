<?php

declare(strict_types=1);

namespace Sesam\Services\Agency;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\AgencyInvoice;
use Sesam\Models\AgencyProject;
use Sesam\Models\AgencyProspect;
use Sesam\Models\AgencyQuote;
use Sesam\Models\User;
use Sesam\Repositories\AgencyProjectRepository;
use Sesam\Repositories\AgencyProspectRepository;
use Sesam\Repositories\AgencyQuoteRepository;
use Sesam\Services\Service;

final class AgencyService extends Service
{
    private const MAX_AMOUNT = 100_000_000;
    private const PRE_QUOTE_STAGES = ['nouveau', 'contacte', 'rendez-vous'];

    private AgencyProspectRepository $prospects;
    private AgencyQuoteRepository $quotes;
    private AgencyProjectRepository $projects;
    private ?\PDO $pdo = null;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        private readonly array $database,
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $actor): array
    {
        $this->assertCan($actor, Permission::AGENCY_VIEW);
        $this->connect();
        $today = $this->today();
        $month = substr($today, 0, 7);

        return [
            'stageCounts' => $this->prospects->stageCounts(),
            'pipeline' => $this->quotes->openPipeline(),
            'capacity' => $this->capacity($month),
            'projectCounts' => $this->projects->statusCounts(),
            'invoiceTotals' => $this->projects->invoiceTotals($today, $month),
            'requests' => $this->prospects->pendingRequests(5),
            'pendingRequests' => $this->prospects->countPendingRequests(),
            'dueActions' => $this->prospects->countDueActions($today),
            'today' => $today,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function prospects(User $actor, array $query): array
    {
        $this->assertCan($actor, Permission::AGENCY_VIEW);
        $this->connect();
        $q = mb_substr($this->text($query, 'q'), 0, 80);
        $stage = $this->text($query, 'etape');
        $stage = array_key_exists($stage, AgencyCatalog::STAGES) ? $stage : '';
        $clients = $this->text($query, 'vue') === 'clients';

        return [
            'prospects' => $this->prospects->search($q, $stage, $clients),
            'stageCounts' => $this->prospects->stageCounts(),
            'requests' => $this->prospects->pendingRequests(50),
            'filters' => ['q' => $q, 'stage' => $stage, 'clients' => $clients],
            'today' => $this->today(),
        ];
    }

    /**
     * @return array{prospect: AgencyProspect, events: list<array<string, mixed>>, quotes: list<AgencyQuote>, projects: list<AgencyProject>, today: string}
     */
    public function prospect(User $actor, int $id): array
    {
        $this->assertCan($actor, Permission::AGENCY_VIEW);
        $this->connect();

        return [
            'prospect' => $this->requireProspect($id),
            'events' => $this->prospects->events($id),
            'quotes' => $this->quotes->forProspect($id),
            'projects' => $this->projects->forProspect($id),
            'today' => $this->today(),
        ];
    }

    public function findProspect(User $actor, int $id): AgencyProspect
    {
        $this->assertCan($actor, Permission::AGENCY_PROSPECTS);
        $this->connect();

        return $this->requireProspect($id);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createProspect(User $actor, array $input): int
    {
        $this->assertCan($actor, Permission::AGENCY_PROSPECTS);
        $pdo = $this->connect();
        $data = $this->prospectData($input);

        $pdo->beginTransaction();
        try {
            $id = $this->prospects->insert($data + [
                'source' => 'manuel',
                'contact_request_id' => null,
                'stage' => 'nouveau',
                'created_by' => $actor->id(),
            ]);
            $this->prospects->addEvent($id, 'created', 'Prospect ajouté à la main.', $actor->id());
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        $this->logger?->info('agency prospect=' . $id . ' created actor=' . $actor->id());

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateProspect(User $actor, int $id, array $input): void
    {
        $this->assertCan($actor, Permission::AGENCY_PROSPECTS);
        $this->connect();
        $this->requireProspect($id);
        $this->prospects->update($id, $this->prospectData($input));
        $this->prospects->addEvent($id, 'updated', 'Coordonnées mises à jour.', $actor->id());
    }

    /**
     * @return array{requests: list<array<string, mixed>>, view: string, pending: int}
     */
    public function requests(User $actor, string $view): array
    {
        $this->assertCan($actor, Permission::AGENCY_VIEW);
        $this->connect();
        $view = in_array($view, ['suivies', 'toutes'], true) ? $view : 'nouvelles';

        return [
            'requests' => $this->prospects->requests($view),
            'view' => $view,
            'pending' => $this->prospects->countPendingRequests(),
        ];
    }

    public function convertRequest(User $actor, int $requestId): int
    {
        $this->assertCan($actor, Permission::AGENCY_PROSPECTS);
        $pdo = $this->connect();
        $request = $this->prospects->findRequest($requestId);
        if ($request === null) {
            throw new HttpException(404, 'Demande introuvable.');
        }
        if ($request['prospect_id'] !== null) {
            throw new ValidationException(['request' => 'Cette demande est déjà suivie comme prospect.']);
        }
        $activity = (string) $request['activity'];

        $pdo->beginTransaction();
        try {
            $id = $this->prospects->insert([
                'full_name' => mb_substr((string) $request['full_name'], 0, 120),
                'business_name' => '',
                'email' => mb_substr((string) $request['email'], 0, 190),
                'phone' => mb_substr((string) $request['phone'], 0, 30),
                'activity' => array_key_exists($activity, AgencyCatalog::activities()) ? $activity : 'autre',
                'source' => 'site',
                'contact_request_id' => $requestId,
                'stage' => 'nouveau',
                'next_action_on' => $this->today(),
                'created_by' => $actor->id(),
            ]);
            $this->prospects->addEvent($id, 'created', 'Demande du site : ' . (string) $request['message'], $actor->id());
            $pdo->commit();
        } catch (\PDOException $exception) {
            $pdo->rollBack();
            if ($exception->getCode() === '23000') {
                throw new ValidationException(['request' => 'Cette demande est déjà suivie comme prospect.']);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        $this->logger?->info('agency prospect=' . $id . ' from request=' . $requestId . ' actor=' . $actor->id());

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function changeStage(User $actor, int $id, array $input): string
    {
        $this->assertCan($actor, Permission::AGENCY_PROSPECTS);
        $this->connect();
        $prospect = $this->requireProspect($id);
        $stage = $this->text($input, 'stage');
        if (!in_array($stage, AgencyCatalog::MANUAL_STAGES, true)) {
            throw new ValidationException(['stage' => 'Choisissez une étape proposée.']);
        }
        if ($prospect->isClient()) {
            throw new ValidationException(['stage' => 'Ce client avance avec ses projets. Son étape ne se change plus à la main.']);
        }
        if ($stage === $prospect->stage()) {
            return 'Le prospect est déjà à cette étape.';
        }
        $this->prospects->updateStage($id, $stage);
        $note = $this->note($input, 'note', 300);
        $this->prospects->addEvent(
            $id,
            'stage',
            $prospect->stageLabel() . ' → ' . AgencyCatalog::STAGES[$stage] . ($note !== '' ? ' · ' . $note : ''),
            $actor->id()
        );

        return 'Étape : ' . AgencyCatalog::STAGES[$stage] . '.';
    }

    /**
     * @param array<string, mixed> $input
     */
    public function addNote(User $actor, int $id, array $input): void
    {
        $this->assertCan($actor, Permission::AGENCY_PROSPECTS);
        $this->connect();
        $prospect = $this->requireProspect($id);
        $note = $this->note($input, 'note', 500);
        $next = $this->optionalDate($input['next_action_on'] ?? null, 'next_action_on');
        if ($note === '' && $next === $prospect->nextActionOn()) {
            throw new ValidationException(['note' => 'Écrivez une note ou changez la date de relance.']);
        }
        $this->prospects->update($id, [
            'full_name' => $prospect->fullName(),
            'business_name' => $prospect->businessName(),
            'email' => $prospect->email(),
            'phone' => $prospect->phone(),
            'activity' => $prospect->activity(),
            'next_action_on' => $next,
        ]);
        $body = $note !== '' ? $note : 'Relance';
        if ($next !== $prospect->nextActionOn()) {
            $body .= $next === null ? ' · relance retirée' : ' · relance le ' . $this->frenchDate($next);
        }
        $this->prospects->addEvent($id, 'note', $body, $actor->id());
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createQuote(User $actor, int $prospectId, array $input): string
    {
        $this->assertCan($actor, Permission::AGENCY_QUOTES);
        $this->connect();
        $prospect = $this->requireProspect($prospectId);
        if ($prospect->stage() === 'perdu') {
            throw new ValidationException(['offer' => 'Ce prospect est marqué perdu. Repassez-le à une étape active avant de chiffrer.']);
        }
        $offer = $this->text($input, 'offer');
        if (!array_key_exists($offer, AgencyCatalog::OFFERS)) {
            throw new ValidationException(['offer' => 'Choisissez une formule.']);
        }
        $sitePath = $this->text($input, 'site_path');
        if (!array_key_exists($sitePath, AgencyCatalog::SITE_PATHS)) {
            throw new ValidationException(['site_path' => 'Indiquez s\'il s\'agit d\'un site neuf ou d\'un site déjà là.']);
        }
        $amount = $this->amount($input['amount'] ?? null, 'amount');
        $minimum = AgencyCatalog::OFFERS[$offer]['min'];
        if ($amount < $minimum) {
            throw new ValidationException(['amount' => 'La formule ' . AgencyCatalog::OFFERS[$offer]['label'] . ' démarre à ' . $this->money($minimum) . '.']);
        }
        $description = $this->note($input, 'description', 2000);

        $id = $this->quotes->insert([
            'prospect_id' => $prospectId,
            'offer' => $offer,
            'site_path' => $sitePath,
            'amount' => $amount,
            'description' => $description,
        ]);
        $quote = $this->quotes->find($id);
        $reference = $quote?->reference() ?? '';
        $this->prospects->addEvent($prospectId, 'quote', 'Devis ' . $reference . ' préparé : ' . AgencyCatalog::OFFERS[$offer]['label'] . ', ' . $this->money($amount) . '.', $actor->id());

        return 'Devis ' . $reference . ' préparé en brouillon.';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{message: string, prospectId: int}
     */
    public function quoteStatus(User $actor, int $quoteId, array $input): array
    {
        $this->assertCan($actor, Permission::AGENCY_QUOTES);
        $pdo = $this->connect();
        $status = $this->text($input, 'status');

        $pdo->beginTransaction();
        try {
            $quote = $this->quotes->find($quoteId);
            if ($quote === null) {
                throw new HttpException(404, 'Devis introuvable.');
            }
            $allowed = match ($quote->status()) {
                'draft' => ['sent', 'refused'],
                'sent' => ['accepted', 'refused'],
                default => [],
            };
            if (!in_array($status, $allowed, true)) {
                throw new ValidationException(['status' => 'Ce devis ne peut plus passer à cet état.']);
            }
            $prospect = $this->requireProspect($quote->prospectId());
            $this->quotes->updateStatus($quoteId, $status);
            $message = match ($status) {
                'sent' => $this->quoteSent($actor, $prospect, $quote),
                'accepted' => $this->quoteAccepted($actor, $prospect, $quote),
                default => $this->quoteRefused($actor, $prospect, $quote),
            };
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        $this->logger?->info('agency quote=' . $quoteId . ' status=' . $status . ' actor=' . $actor->id());

        return ['message' => $message, 'prospectId' => $quote->prospectId()];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function projects(User $actor, array $query): array
    {
        $this->assertCan($actor, Permission::AGENCY_VIEW);
        $this->connect();
        $status = $this->text($query, 'statut');
        $status = array_key_exists($status, AgencyCatalog::PROJECT_STATUSES) ? $status : '';
        $today = $this->today();

        return [
            'projects' => $this->projects->list($status),
            'counts' => $this->projects->statusCounts(),
            'capacity' => $this->capacity(substr($today, 0, 7)),
            'statusFilter' => $status,
        ];
    }

    /**
     * @return array{project: AgencyProject, invoices: list<AgencyInvoice>, capacity: array<string, mixed>, today: string}
     */
    public function project(User $actor, int $id): array
    {
        $this->assertCan($actor, Permission::AGENCY_VIEW);
        $this->connect();
        $project = $this->requireProject($id);
        $today = $this->today();

        return [
            'project' => $project,
            'invoices' => $this->projects->invoices($id, '', $today),
            'capacity' => $this->capacity(substr($today, 0, 7)),
            'today' => $today,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function projectStatus(User $actor, int $id, array $input): string
    {
        $this->assertCan($actor, Permission::AGENCY_PROJECTS);
        $pdo = $this->connect();
        $action = $this->text($input, 'action');
        $today = $this->today();
        $siteUrl = $action === 'deliver' ? $this->siteUrl($input['site_url'] ?? null) : '';

        $pdo->beginTransaction();
        try {
            $project = $this->requireProject($id);
            if ($action === 'start') {
                if ($project->status() !== 'planned') {
                    throw new ValidationException(['action' => 'Ce projet a déjà démarré.']);
                }
                $month = substr($today, 0, 7);
                if ($this->projects->countStartedIn($month) >= AgencyCatalog::MONTHLY_CAPACITY) {
                    throw new ValidationException(['action' => 'Capacité atteinte : ' . AgencyCatalog::MONTHLY_CAPACITY . ' projets ont déjà démarré ce mois-ci. Démarrez celui-ci le mois prochain.']);
                }
                $this->projects->start($id, $month, $today);
                $this->prospects->updateStage($project->prospectId(), 'projet-en-cours');
                $this->prospects->addEvent($project->prospectId(), 'project', 'Projet « ' . $project->title() . ' » démarré.', $actor->id());
                $message = 'Projet démarré. Il compte dans la capacité du mois.';
            } elseif ($action === 'deliver') {
                if ($project->status() !== 'in_progress') {
                    throw new ValidationException(['action' => 'Seul un projet en cours peut être livré.']);
                }
                $this->projects->deliver($id, $today, $siteUrl);
                $this->prospects->updateStage($project->prospectId(), 'livre');
                $this->prospects->addEvent($project->prospectId(), 'project', 'Projet « ' . $project->title() . ' » livré.', $actor->id());
                $message = 'Projet livré.';
            } else {
                throw new ValidationException(['action' => 'Action inconnue.']);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        $this->logger?->info('agency project=' . $id . ' action=' . $action . ' actor=' . $actor->id());

        return $message;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createInvoice(User $actor, int $projectId, array $input): string
    {
        $this->assertCan($actor, Permission::AGENCY_INVOICES);
        $pdo = $this->connect();
        $label = $this->note($input, 'label', 120);
        if ($label === '') {
            throw new ValidationException(['label' => 'Indiquez l\'objet de la facture (acompte, solde…).']);
        }
        $amount = $this->amount($input['amount'] ?? null, 'amount');
        $dueOn = $this->optionalDate($input['due_on'] ?? null, 'due_on');
        if ($dueOn === null) {
            throw new ValidationException(['due_on' => 'Indiquez une date d\'échéance.']);
        }

        $pdo->beginTransaction();
        try {
            $project = $this->requireProject($projectId);
            $remaining = $project->amount() - $this->projects->invoicedTotal($projectId);
            if ($amount > $remaining) {
                throw new ValidationException(['amount' => 'Il reste ' . $this->money(max(0, $remaining)) . ' à facturer sur ce projet.']);
            }
            $id = $this->projects->insertInvoice([
                'project_id' => $projectId,
                'label' => $label,
                'amount' => $amount,
                'due_on' => $dueOn,
            ]);
            $invoice = $this->projects->findInvoice($id);
            $this->prospects->addEvent($project->prospectId(), 'invoice', 'Facture ' . ($invoice?->reference() ?? '') . ' émise : ' . $this->money($amount) . '.', $actor->id());
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        $this->logger?->info('agency invoice=' . $id . ' project=' . $projectId . ' actor=' . $actor->id());

        return 'Facture ' . ($invoice?->reference() ?? '') . ' émise.';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{message: string, projectId: int}
     */
    public function payInvoice(User $actor, int $id, array $input): array
    {
        $this->assertCan($actor, Permission::AGENCY_INVOICES);
        $this->connect();
        $invoice = $this->projects->findInvoice($id);
        if ($invoice === null) {
            throw new HttpException(404, 'Facture introuvable.');
        }
        $method = $this->text($input, 'payment_method');
        if (!array_key_exists($method, AgencyCatalog::PAYMENT_METHODS)) {
            throw new ValidationException(['payment_method' => 'Choisissez le moyen de paiement reçu.']);
        }
        $today = $this->today();
        $paidOn = $this->optionalDate($input['paid_on'] ?? null, 'paid_on') ?? $today;
        if ($paidOn > $today) {
            throw new ValidationException(['paid_on' => 'La date de paiement ne peut pas être dans le futur.']);
        }
        if (!$this->projects->markPaid($id, $paidOn, $method)) {
            throw new ValidationException(['payment_method' => 'Cette facture est déjà réglée.']);
        }
        $project = $this->requireProject($invoice->projectId());
        $this->prospects->addEvent($project->prospectId(), 'invoice', 'Facture ' . $invoice->reference() . ' réglée (' . AgencyCatalog::PAYMENT_METHODS[$method] . ').', $actor->id());
        $this->logger?->info('agency invoice=' . $id . ' paid method=' . $method . ' actor=' . $actor->id());

        return ['message' => 'Paiement enregistré pour ' . $invoice->reference() . '.', 'projectId' => $invoice->projectId()];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function invoices(User $actor, array $query): array
    {
        $this->assertCan($actor, Permission::AGENCY_VIEW);
        $this->connect();
        $status = $this->text($query, 'statut');
        $status = in_array($status, ['issued', 'paid', 'overdue'], true) ? $status : '';
        $today = $this->today();

        return [
            'invoices' => $this->projects->invoices(null, $status, $today),
            'totals' => $this->projects->invoiceTotals($today, substr($today, 0, 7)),
            'statusFilter' => $status,
            'today' => $today,
        ];
    }

    private function quoteSent(User $actor, AgencyProspect $prospect, AgencyQuote $quote): string
    {
        if (in_array($prospect->stage(), self::PRE_QUOTE_STAGES, true)) {
            $this->prospects->updateStage($prospect->id(), 'devis-envoye');
        }
        $this->prospects->addEvent($prospect->id(), 'quote', 'Devis ' . $quote->reference() . ' envoyé.', $actor->id());

        return 'Devis ' . $quote->reference() . ' marqué envoyé.';
    }

    private function quoteAccepted(User $actor, AgencyProspect $prospect, AgencyQuote $quote): string
    {
        $this->projects->insert([
            'prospect_id' => $prospect->id(),
            'quote_id' => $quote->id(),
            'title' => mb_substr('Site ' . $quote->offerLabel() . ' · ' . $prospect->displayName(), 0, 160),
            'offer' => $quote->offer(),
            'amount' => $quote->amount(),
        ]);
        if (!in_array($prospect->stage(), ['projet-en-cours', 'livre'], true)) {
            $this->prospects->updateStage($prospect->id(), 'gagne');
        }
        $this->prospects->addEvent($prospect->id(), 'quote', 'Devis ' . $quote->reference() . ' accepté. Projet créé, à démarrer.', $actor->id());

        return 'Devis accepté. Le projet est créé et attend son démarrage.';
    }

    private function quoteRefused(User $actor, AgencyProspect $prospect, AgencyQuote $quote): string
    {
        $this->prospects->addEvent($prospect->id(), 'quote', 'Devis ' . $quote->reference() . ' refusé.', $actor->id());

        return 'Devis ' . $quote->reference() . ' marqué refusé.';
    }

    /**
     * @return array{month: string, used: int, limit: int, projects: list<AgencyProject>}
     */
    private function capacity(string $month): array
    {
        $projects = $this->projects->startedIn($month);

        return [
            'month' => $month,
            'used' => count($projects),
            'limit' => AgencyCatalog::MONTHLY_CAPACITY,
            'projects' => $projects,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{full_name: string, business_name: string, email: string, phone: string, activity: string, next_action_on: ?string}
     */
    private function prospectData(array $input): array
    {
        $errors = [];
        $fullName = $this->text($input, 'full_name');
        $businessName = $this->text($input, 'business_name');
        $email = $this->text($input, 'email');
        $phone = $this->text($input, 'phone');
        $activity = $this->text($input, 'activity');
        if ($fullName === '' || mb_strlen($fullName) > 120) {
            $errors['full_name'] = 'Indiquez le nom du contact (120 caractères au plus).';
        }
        if (mb_strlen($businessName) > 120) {
            $errors['business_name'] = 'Le nom du commerce ne doit pas dépasser 120 caractères.';
        }
        if ($email !== '' && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190)) {
            $errors['email'] = 'Indiquez une adresse e-mail valide.';
        }
        if ($phone !== '' && preg_match('/^[0-9 +().-]{6,30}$/', $phone) !== 1) {
            $errors['phone'] = 'Indiquez un numéro de téléphone valide.';
        }
        if ($email === '' && $phone === '' && !isset($errors['email'])) {
            $errors['phone'] = 'Indiquez au moins un téléphone ou un e-mail.';
        }
        if (!array_key_exists($activity, AgencyCatalog::activities())) {
            $errors['activity'] = 'Choisissez une activité proposée.';
        }
        try {
            $next = $this->optionalDate($input['next_action_on'] ?? null, 'next_action_on');
        } catch (ValidationException $exception) {
            $errors += $exception->errors();
            $next = null;
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'full_name' => $fullName,
            'business_name' => $businessName,
            'email' => $email,
            'phone' => $phone,
            'activity' => $activity,
            'next_action_on' => $next,
        ];
    }

    private function connect(): \PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Connection::make($this->database);
            $this->prospects = new AgencyProspectRepository($this->pdo);
            $this->quotes = new AgencyQuoteRepository($this->pdo);
            $this->projects = new AgencyProjectRepository($this->pdo);
        }

        return $this->pdo;
    }

    private function requireProspect(int $id): AgencyProspect
    {
        $prospect = $this->prospects->find($id);
        if ($prospect === null) {
            throw new HttpException(404, 'Prospect introuvable.');
        }

        return $prospect;
    }

    private function requireProject(int $id): AgencyProject
    {
        $project = $this->projects->find($id);
        if ($project === null) {
            throw new HttpException(404, 'Projet introuvable.');
        }

        return $project;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function text(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    }

    /**
     * @param array<string, mixed> $input
     */
    private function note(array $input, string $key, int $max): string
    {
        $value = $this->text($input, $key);
        if (mb_strlen($value) > $max) {
            throw new ValidationException([$key => 'Ce texte ne doit pas dépasser ' . $max . ' caractères.']);
        }

        return $value;
    }

    private function amount(mixed $value, string $field): int
    {
        $raw = is_string($value) ? str_replace([' ', "\u{202F}", "\u{00A0}"], '', trim($value)) : '';
        if (preg_match('/^\d{1,9}$/', $raw) !== 1 || (int) $raw < 1 || (int) $raw > self::MAX_AMOUNT) {
            throw new ValidationException([$field => 'Indiquez un montant entier en FCFA.']);
        }

        return (int) $raw;
    }

    private function optionalDate(mixed $value, string $field): ?string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $raw) {
            throw new ValidationException([$field => 'Indiquez une date valide.']);
        }

        return $raw;
    }

    private function siteUrl(mixed $value): string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return '';
        }
        if (mb_strlen($raw) > 255 || filter_var($raw, FILTER_VALIDATE_URL) === false || preg_match('#^https?://#i', $raw) !== 1) {
            throw new ValidationException(['site_url' => 'Indiquez une adresse commençant par http:// ou https://.']);
        }

        return $raw;
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', "\u{202F}") . ' FCFA';
    }

    private function frenchDate(string $date): string
    {
        return (new \DateTimeImmutable($date))->format('d/m/Y');
    }

    private function today(): string
    {
        return gmdate('Y-m-d');
    }

    private function assertCan(User $actor, string $permission): void
    {
        if ($actor->tenantId() !== null || !$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
    }
}
