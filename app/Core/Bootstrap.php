<?php

declare(strict_types=1);

namespace Sesam\Core;

use Sesam\Controllers\AccountController;
use Sesam\Controllers\AdminController;
use Sesam\Controllers\AgencyController;
use Sesam\Controllers\ClientController;
use Sesam\Controllers\AuthController;
use Sesam\Controllers\BoutiqueOrderController;
use Sesam\Controllers\BoutiqueProductController;
use Sesam\Controllers\BoutiqueSettingsController;
use Sesam\Controllers\DesignController;
use Sesam\Controllers\HomeController;
use Sesam\Controllers\SiteController;
use Sesam\Controllers\NotificationController;
use Sesam\Controllers\PaymentController;
use Sesam\Controllers\PortalController;
use Sesam\Controllers\ProfileController;
use Sesam\Controllers\ReservationAppointmentController;
use Sesam\Controllers\ReservationPrestationController;
use Sesam\Controllers\ReservationSalonController;
use Sesam\Controllers\ReservationTeamController;
use Sesam\Controllers\SearchController;
use Sesam\Controllers\StatisticsController;
use Sesam\Controllers\TenantController;
use Sesam\Controllers\UserController;
use Sesam\Controllers\WebhookController;
use Sesam\Core\Database\Connection;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Router\Router;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Security\ErrorHandler;
use Sesam\Core\Security\SecurityHeaders;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Middleware\AuthMiddleware;
use Sesam\Middleware\CsrfMiddleware;
use Sesam\Middleware\SessionMiddleware;
use Sesam\Middleware\TenantMiddleware;
use Sesam\Repositories\TenantRepository;
use Sesam\Services\Admin\AdminService;
use Sesam\Services\Agency\AgencyService;
use Sesam\Services\AuthService;
use Sesam\Services\Boutique\BoutiqueService;
use Sesam\Services\ClientService;
use Sesam\Services\DashboardService;
use Sesam\Services\Storage\ImageUploadService;
use Sesam\Services\Loyer\RentService;
use Sesam\Services\MessagingService;
use Sesam\Services\NotificationService;
use Sesam\Services\PaymentService;
use Sesam\Services\Portal\PublicPortalService;
use Sesam\Services\ProfileService;
use Sesam\Services\QuoteService;
use Sesam\Services\Reservation\BookingService;
use Sesam\Services\Reservation\SalonService;
use Sesam\Services\SearchService;
use Sesam\Services\StatisticsService;
use Sesam\Services\TenantConsoleService;
use Sesam\Services\TenantService;
use Sesam\Services\UserService;

final class Bootstrap
{
    public static function kernel(string $root): Kernel
    {
        Env::load($root . DIRECTORY_SEPARATOR . '.env');

        $views = new ViewRenderer($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views');
        $logger = new Logger($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.log');
        $session = new Session();
        $csrf = new Csrf($session);
        /** @var array{db: array{host: string, port: string, name: string, user: string, password: string}, url: string} $config */
        $config = require $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        /** @var array{gateway: string, naboopay: array{api_key: string, webhook_secret: string, api_base: string, signature_header: string}} $paymentConfig */
        $paymentConfig = require $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'payments.php';
        /** @var array{mode: string, mail: array{from: string, smtp_host: string, smtp_port: string, smtp_user: string, smtp_password: string}, whatsapp: array{api_url: string, api_token: string}} $notificationConfig */
        $notificationConfig = require $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'notifications.php';
        $tenantContext = new TenantContext();
        $auth = new AuthService($config['db'], $session);
        $mailer = new \Sesam\Core\Mail\Mailer(require $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'mail.php', $logger);
        \Sesam\Core\Mail\Mailer::setCurrent($mailer);
        \Sesam\Services\WelcomeService::setCurrent(new \Sesam\Services\WelcomeService($config['db']));
        $auth->useMailer($mailer, $config['url']);
        $users = new UserService($config['db'], $tenantContext, $logger);
        $tenants = new TenantService($config['db'], $tenantContext, $logger);
        $clients = new ClientService($config['db'], $tenantContext, $logger);
        $payments = new PaymentService($config['db'], $tenantContext, $paymentConfig, $config['url'], $logger);
        $dashboard = new DashboardService($config['db']);
        $firebaseConfig = require $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'firebase.php';
        $notifier = new \Sesam\Services\Notifier($config['db'], $firebaseConfig, $logger);
        \Sesam\Services\Notifier::setCurrent($notifier);
        \Sesam\Services\DailyNotifications::setCurrent(!empty($firebaseConfig['daily_auto'])
            ? new \Sesam\Services\DailyNotifications($config['db'], $notifier, $firebaseConfig['cache_dir'] . DIRECTORY_SEPARATOR . 'daily-notifications.txt')
            : null);
        $notifications = new NotificationService($config['db'], $tenantContext);
        $messaging = new MessagingService($config['db'], $tenantContext, $notificationConfig, $logger);
        \Sesam\Services\ReminderPump::setCurrent(new \Sesam\Services\ReminderPump($messaging, $firebaseConfig['cache_dir'] . DIRECTORY_SEPARATOR . 'reminders-pump.txt'));
        $booking = new BookingService($config['db'], $tenantContext, $messaging, $payments, $logger);
        $rent = new RentService($config['db'], $tenantContext, $payments, $messaging);
        $immo = new \Sesam\Services\Immo\ImmoService($config['db'], $tenantContext, $payments, $messaging);
        $saas = new \Sesam\Services\SaasService($config['db']);
        $payments->useSubscriptionSettler($saas);
        $saas->usePayments($payments);
        /** @var array{max_bytes: int, max_side: int, quality: int} $uploads */
        $uploads = require $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'uploads.php';
        $images = new ImageUploadService(
            $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads',
            (int) $uploads['max_bytes'],
            (int) $uploads['max_side'],
            (int) $uploads['quality']
        );
        $boutique = new BoutiqueService($config['db'], $tenantContext, $images, $config['url']);
        $booking->useImages($images);
        $immo->useImages(new ImageUploadService(
            $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads',
            (int) $uploads['max_bytes'],
            1280,
            80
        ));
        $salon = new SalonService($config['db'], $tenantContext, $images, $config['url']);
        $profiles = new ProfileService($config['db']);
        $search = new SearchService($config['db'], $tenantContext);
        $site = new SiteController($views, $csrf, new QuoteService($config['db']));
        $authController = new AuthController($views, $csrf, $session, $auth);
        $adminService = new AdminService($config['db']);
        $account = new AccountController($views, $csrf, $session, $auth, $tenantContext, $dashboard, $clients, $notifications, $boutique, $salon, $adminService, $immo);
        $adminController = new AdminController($views, $csrf, $session, $auth, $adminService);
        $clientController = new ClientController($views, $csrf, $session, $auth, $clients, $tenantContext, $notifications, $boutique, $booking);
        $userController = new UserController($views, $csrf, $session, $auth, $users, $tenantContext, $notifications);
        $tenantController = new TenantController($views, $csrf, $session, $auth, $tenants, $tenantContext, $notifications, new TenantConsoleService($config['db'], $logger), $adminService, $saas);
        $agencyController = new AgencyController($views, $csrf, $session, $auth, $tenantContext, new AgencyService($config['db'], $logger), $notifications);
        $pushController = new \Sesam\Controllers\PushController($views, $csrf, $session, $auth, $tenantContext, $notifications, $notifier, $firebaseConfig);
        $notificationController = new NotificationController($views, $csrf, $session, $auth, $tenantContext, $notifications, $messaging);
        $profileController = new ProfileController($views, $csrf, $session, $auth, $tenantContext, $profiles, $notifications);
        $searchController = new SearchController($views, $csrf, $session, $auth, $tenantContext, $search, $notifications);
        $paymentController = new PaymentController($views, $csrf, $session, $auth, $payments, $tenantContext, $notifications);
        $statisticsController = new StatisticsController($views, $csrf, $session, $auth, $tenantContext, new StatisticsService($config['db'], $tenantContext), $notifications);
        $webhookController = new WebhookController($views, $payments, $paymentConfig);
        $reservationPrestations = new ReservationPrestationController($views, $csrf, $session, $auth, $tenantContext, $booking, $notifications);
        $reservationTeam = new ReservationTeamController($views, $csrf, $session, $auth, $tenantContext, $booking, $notifications);
        $reservationAppointments = new ReservationAppointmentController($views, $csrf, $session, $auth, $tenantContext, $booking, $notifications);
        $reservationSalon = new ReservationSalonController($views, $csrf, $session, $auth, $tenantContext, $booking, $salon, $notifications);
        $boutiqueProducts = new BoutiqueProductController($views, $csrf, $session, $auth, $tenantContext, $boutique, $notifications);
        $boutiqueOrders = new BoutiqueOrderController($views, $csrf, $session, $auth, $tenantContext, $boutique, $notifications);
        $boutiqueSettings = new BoutiqueSettingsController($views, $csrf, $session, $auth, $tenantContext, $boutique, $notifications);
        $saasController = new \Sesam\Controllers\SaasController($views, $csrf, $session, $auth, $tenantContext, $saas, $notifications);
        $immoController = new \Sesam\Controllers\ImmoController($views, $csrf, $session, $auth, $tenantContext, $immo, $notifications);
        $portal = new PortalController(
            $views,
            $csrf,
            new PublicPortalService(
                $config['db'],
                $tenantContext,
                $session,
                $booking,
                $rent,
                $payments,
                $messaging,
            ),
            $boutique,
            $salon,
            $booking,
            $immo,
        );
        $router = new Router();
        $router->get('/t/{slug}/paiement/{id}/bac-a-sable', [$portal, 'sandbox']);
        $router->get('/t/{slug}/paiement/{id}/retour', [$portal, 'returnFromProvider']);
        $router->post('/t/{slug}/paiement/{id}/confirmer', [$portal, 'sandboxConfirm']);
        $router->post('/t/{slug}/paiement/{id}/annuler', [$portal, 'sandboxCancel']);
        $router->get('/t/{slug}/confirmation', [$portal, 'confirmation']);
        $router->post('/t/{slug}/commander', [$portal, 'order']);
        $router->get('/t/{slug}/creneaux', [$portal, 'slots']);
        $router->get('/t/{slug}/produit/{id}/image', [$portal, 'productImage']);
        $router->get('/t/{slug}/prestation/{id}/image', [$portal, 'prestationImage']);
        $router->get('/t/{slug}/bien/{id}/image', [$portal, 'propertyImage']);
        $router->get('/t/{slug}/produit/{id}', [$portal, 'product']);
        $router->get('/t/{slug}/logo', [$portal, 'logo']);
        $router->get('/t/{slug}/couverture', [$portal, 'cover']);
        $router->get('/t/{slug}', [$portal, 'home']);
        $router->post('/t/{slug}', [$portal, 'submit']);
        $router->get('/', [new HomeController($views), 'index']);
        $router->get('/a-propos', [$site, 'about']);
        $router->get('/services', [$site, 'services']);
        $router->get('/services/{key}', [$site, 'service']);
        $router->get('/offres', [$site, 'offers']);
        $router->get('/portfolio', [$site, 'portfolio']);
        $router->get('/contact', [$site, 'contact']);
        $router->get('/devis', [$site, 'quoteForm']);
        $router->post('/devis', [$site, 'quoteSubmit']);
        $router->get('/design-system', [new DesignController($views), 'index']);
        $router->get('/connexion', [$authController, 'loginForm']);
        $router->post('/connexion', [$authController, 'login']);
        $router->post('/deconnexion', [$authController, 'logout']);
        $router->get('/mot-de-passe', [$authController, 'forgotForm']);
        $router->post('/mot-de-passe', [$authController, 'forgot']);
        $router->get('/mot-de-passe/reinitialiser/{token}', [$authController, 'resetForm']);
        $router->post('/mot-de-passe/reinitialiser/{token}', [$authController, 'reset']);
        $router->get('/espace', [$account, 'show']);
        $router->get('/utilisateurs', [$userController, 'index']);
        $router->get('/utilisateurs/nouveau', [$userController, 'createForm']);
        $router->post('/utilisateurs', [$userController, 'store']);
        $router->get('/utilisateurs/{id}', [$userController, 'editForm']);
        $router->post('/utilisateurs/{id}', [$userController, 'update']);
        $router->post('/utilisateurs/{id}/lien-reinitialisation', [$userController, 'resetLink']);
        $router->get('/commerces', [$tenantController, 'index']);
        $router->get('/commerces/nouveau', [$tenantController, 'createForm']);
        $router->post('/commerces', [$tenantController, 'store']);
        $router->get('/commerces/{id}', [$tenantController, 'show']);
        $router->get('/commerces/{id}/modifier', [$tenantController, 'editForm']);
        $router->post('/commerces/{id}', [$tenantController, 'update']);
        $router->post('/commerces/{id}/abonnement', [$tenantController, 'subscription']);
        $router->post('/commerces/{id}/encaissement', [$tenantController, 'payment']);
        $router->post('/commerces/{id}/notes', [$tenantController, 'note']);
        $router->post('/commerces/{id}/essai', [$tenantController, 'trial']);
        $router->get('/revenus', [$adminController, 'revenue']);
        $router->get('/revenus/export', [$adminController, 'export']);
        $router->get('/finance/statistiques', [$adminController, 'statistics']);
        $router->get('/tarifs', [$adminController, 'prices']);
        $router->post('/tarifs', [$adminController, 'savePrices']);
        $router->get('/agence', [$agencyController, 'dashboard']);
        $router->get('/agence/prospects', [$agencyController, 'prospects']);
        $router->get('/agence/prospects/nouveau', [$agencyController, 'prospectForm']);
        $router->post('/agence/prospects', [$agencyController, 'prospectStore']);
        $router->get('/agence/prospects/{id}', [$agencyController, 'prospectShow']);
        $router->get('/agence/prospects/{id}/modifier', [$agencyController, 'prospectEditForm']);
        $router->post('/agence/prospects/{id}', [$agencyController, 'prospectUpdate']);
        $router->post('/agence/prospects/{id}/etape', [$agencyController, 'prospectStage']);
        $router->post('/agence/prospects/{id}/note', [$agencyController, 'prospectNote']);
        $router->post('/agence/prospects/{id}/devis', [$agencyController, 'quoteStore']);
        $router->get('/agence/demandes', [$agencyController, 'requests']);
        $router->post('/agence/demandes/{id}/convertir', [$agencyController, 'convertRequest']);
        $router->post('/agence/devis/{id}/statut', [$agencyController, 'quoteStatus']);
        $router->get('/agence/projets', [$agencyController, 'projects']);
        $router->get('/agence/projets/{id}', [$agencyController, 'projectShow']);
        $router->post('/agence/projets/{id}/statut', [$agencyController, 'projectStatus']);
        $router->post('/agence/projets/{id}/factures', [$agencyController, 'invoiceStore']);
        $router->get('/agence/factures', [$agencyController, 'invoices']);
        $router->post('/agence/factures/{id}/payer', [$agencyController, 'invoicePay']);
        $router->get('/parametres', [$tenantController, 'settingsForm']);
        $router->post('/parametres', [$tenantController, 'settingsUpdate']);
        $router->get('/firebase-messaging-sw.js', [$pushController, 'serviceWorker']);
        $router->post('/notifications/appareils', [$pushController, 'register']);
        $router->post('/notifications/appareils/oublier', [$pushController, 'unregister']);
        $router->post('/notifications/appareils/{id}/supprimer', [$pushController, 'removeDevice']);
        $router->get('/notifications/reglages', [$pushController, 'settings']);
        $router->post('/notifications/reglages', [$pushController, 'savePreferences']);
        $router->post('/notifications/test', [$pushController, 'test']);
        $router->post('/notifications/tout-lu', [$pushController, 'markAllRead']);
        $router->get('/notifications', [$notificationController, 'index']);
        $router->get('/notifications/historique', [$notificationController, 'history']);
        $router->get('/notifications/envoyer', [$notificationController, 'sendForm']);
        $router->post('/notifications/envoyer', [$notificationController, 'send']);
        $router->post('/notifications/{id}/lu', [$notificationController, 'markRead']);
        $router->get('/profil', [$profileController, 'show']);
        $router->post('/profil', [$profileController, 'update']);
        $router->get('/recherche', [$searchController, 'index']);
        $router->get('/clients', [$clientController, 'index']);
        $router->get('/clients/export', [$clientController, 'export']);
        $router->get('/clients/nouveau', [$clientController, 'createForm']);
        $router->post('/clients', [$clientController, 'store']);
        $router->get('/clients/{id}/modifier', [$clientController, 'editForm']);
        $router->post('/clients/{id}/notes', [$clientController, 'storeNote']);
        $router->post('/clients/{id}/archiver', [$clientController, 'archive']);
        $router->post('/clients/{id}/restaurer', [$clientController, 'restore']);
        $router->get('/clients/{id}', [$clientController, 'show']);
        $router->post('/clients/{id}', [$clientController, 'update']);
        $router->get('/paiements', [$paymentController, 'index']);
        $router->get('/paiements/nouveau', [$paymentController, 'createForm']);
        $router->post('/paiements', [$paymentController, 'store']);
        $router->post('/paiements/{id}/bac-a-sable/confirmer', [$paymentController, 'sandboxConfirm']);
        $router->post('/paiements/{id}/bac-a-sable/annuler', [$paymentController, 'sandboxCancel']);
        $router->get('/paiements/{id}/bac-a-sable', [$paymentController, 'sandbox']);
        $router->get('/paiements/{id}/retour', [$paymentController, 'returnFromProvider']);
        $router->get('/paiements/{id}', [$paymentController, 'show']);
        $router->get('/statistiques', [$statisticsController, 'index']);
        $router->get('/reservation/agenda', [$reservationSalon, 'agenda']);
        $router->get('/reservation/salon', [$reservationSalon, 'form']);
        $router->post('/reservation/salon', [$reservationSalon, 'update']);
        $router->post('/reservation/salon/fermetures', [$reservationSalon, 'addClosure']);
        $router->post('/reservation/salon/fermetures/{id}/supprimer', [$reservationSalon, 'deleteClosure']);
        $router->get('/reservation/creneaux', [$reservationAppointments, 'slots']);
        $router->get('/reservation/clientes', [$reservationAppointments, 'clientSearch']);
        $router->get('/reservation/prestations', [$reservationPrestations, 'index']);
        $router->get('/reservation/prestations/nouveau', [$reservationPrestations, 'createForm']);
        $router->post('/reservation/prestations', [$reservationPrestations, 'store']);
        $router->get('/reservation/prestations/{id}/modifier', [$reservationPrestations, 'editForm']);
        $router->post('/reservation/prestations/{id}/activation', [$reservationPrestations, 'toggle']);
        $router->post('/reservation/prestations/{id}', [$reservationPrestations, 'update']);
        $router->get('/reservation/equipe', [$reservationTeam, 'index']);
        $router->get('/reservation/equipe/nouveau', [$reservationTeam, 'createForm']);
        $router->post('/reservation/equipe', [$reservationTeam, 'store']);
        $router->get('/reservation/equipe/{id}/disponibilites', [$reservationTeam, 'availabilityForm']);
        $router->post('/reservation/equipe/{id}/disponibilites', [$reservationTeam, 'saveAvailability']);
        $router->post('/reservation/equipe/{id}/absences', [$reservationTeam, 'addAbsence']);
        $router->post('/reservation/equipe/{id}/absences/{absence}/supprimer', [$reservationTeam, 'deleteAbsence']);
        $router->get('/reservation/equipe/{id}', [$reservationTeam, 'availabilityForm']);
        $router->post('/reservation/equipe/{id}', [$reservationTeam, 'update']);
        $router->get('/reservation/rendez-vous', [$reservationAppointments, 'index']);
        $router->get('/reservation/rendez-vous/nouveau', [$reservationAppointments, 'createForm']);
        $router->post('/reservation/rendez-vous', [$reservationAppointments, 'store']);
        $router->get('/reservation/rendez-vous/{id}/reprogrammer', [$reservationAppointments, 'rescheduleForm']);
        $router->post('/reservation/rendez-vous/{id}/reprogrammer', [$reservationAppointments, 'reschedule']);
        $router->post('/reservation/rendez-vous/{id}/confirmer', [$reservationAppointments, 'confirm']);
        $router->post('/reservation/rendez-vous/{id}/annuler', [$reservationAppointments, 'cancel']);
        $router->post('/reservation/rendez-vous/{id}/statut', [$reservationAppointments, 'status']);
        $router->post('/reservation/rendez-vous/{id}/paiement', [$reservationAppointments, 'paid']);
        $router->post('/reservation/rendez-vous/{id}/acompte', [$reservationAppointments, 'deposit']);
        $router->get('/reservation/rendez-vous/{id}', [$reservationAppointments, 'show']);
        $router->get('/boutique/produits', [$boutiqueProducts, 'index']);
        $router->get('/boutique/produits/nouveau', [$boutiqueProducts, 'createForm']);
        $router->post('/boutique/produits', [$boutiqueProducts, 'store']);
        $router->get('/boutique/produits/{id}/modifier', [$boutiqueProducts, 'editForm']);
        $router->post('/boutique/produits/{id}/visibilite', [$boutiqueProducts, 'togglePublished']);
        $router->post('/boutique/produits/{id}/supprimer', [$boutiqueProducts, 'destroy']);
        $router->post('/boutique/produits/{id}', [$boutiqueProducts, 'update']);
        $router->get('/boutique/stock', [$boutiqueProducts, 'stock']);
        $router->post('/boutique/stock', [$boutiqueProducts, 'storeMovement']);
        $router->get('/boutique/commandes', [$boutiqueOrders, 'index']);
        $router->get('/boutique/commandes/nouveau', [$boutiqueOrders, 'createForm']);
        $router->post('/boutique/commandes', [$boutiqueOrders, 'store']);
        $router->post('/boutique/commandes/{id}/statut', [$boutiqueOrders, 'status']);
        $router->post('/boutique/commandes/{id}/paiement', [$boutiqueOrders, 'paid']);
        $router->get('/boutique/commandes/{id}', [$boutiqueOrders, 'show']);
        $router->get('/boutique/parametres', [$boutiqueSettings, 'form']);
        $router->post('/boutique/parametres', [$boutiqueSettings, 'update']);
        // Inscription en libre-service et abonnement du commerce
        $router->get('/commencer', [$saasController, 'signupForm']);
        $router->post('/commencer', [$saasController, 'signup']);
        $router->get('/abonnement', [$saasController, 'subscription']);
        $router->post('/abonnement/payer', [$saasController, 'checkout']);
        $router->post('/abonnement/payer/confirmer', [$saasController, 'confirm']);
        $router->get('/abonnement/retour/{id}', [$saasController, 'returnFromProvider']);

        // Sesam Immo (ex-Loyer)
        $router->get('/immo/loyers', [$immoController, 'dues']);
        $router->get('/immo/loyers/{id}', [$immoController, 'due']);
        $router->post('/immo/loyers/{id}/paiement', [$immoController, 'pay']);
        $router->post('/immo/loyers/{id}/lien', [$immoController, 'link']);
        $router->post('/immo/loyers/{id}/relance', [$immoController, 'remind']);
        $router->get('/immo/quittances/{id}', [$immoController, 'receipt']);
        $router->get('/immo/locataires', [$immoController, 'tenants']);
        $router->get('/immo/locataires/nouveau', [$immoController, 'tenantForm']);
        $router->post('/immo/locataires', [$immoController, 'tenantSave']);
        $router->get('/immo/locataires/{id}/modifier', [$immoController, 'tenantForm']);
        $router->post('/immo/locataires/{id}', [$immoController, 'tenantSave']);
        $router->get('/immo/locataires/{id}', [$immoController, 'tenant']);
        $router->get('/immo/biens', [$immoController, 'properties']);
        $router->get('/immo/biens/nouveau', [$immoController, 'propertyForm']);
        $router->post('/immo/biens', [$immoController, 'propertySave']);
        $router->get('/immo/biens/{id}/modifier', [$immoController, 'propertyForm']);
        $router->post('/immo/biens/{id}/logements', [$immoController, 'unitStore']);
        $router->post('/immo/biens/{id}', [$immoController, 'propertySave']);
        $router->get('/immo/biens/{id}', [$immoController, 'property']);
        $router->get('/immo/logements/{id}/modifier', [$immoController, 'unitForm']);
        $router->post('/immo/logements/{id}', [$immoController, 'unitSave']);
        $router->get('/immo/baux', [$immoController, 'leases']);
        $router->get('/immo/baux/nouveau', [$immoController, 'leaseForm']);
        $router->post('/immo/baux', [$immoController, 'leaseStore']);
        $router->post('/immo/baux/{id}/caution', [$immoController, 'deposit']);
        $router->post('/immo/baux/{id}/terminer', [$immoController, 'endLease']);
        $router->get('/immo/baux/{id}', [$immoController, 'lease']);
        $router->get('/immo/interventions', [$immoController, 'interventions']);
        $router->post('/immo/interventions', [$immoController, 'interventionStore']);
        $router->post('/immo/interventions/{id}', [$immoController, 'interventionUpdate']);
        $router->get('/immo/relances', [$immoController, 'reminders']);
        $router->post('/immo/relances/lancer', [$immoController, 'runReminders']);
        $router->get('/immo/parametres', [$immoController, 'settings']);
        $router->post('/immo/parametres', [$immoController, 'saveSettings']);
        foreach (['/loyer/biens', '/loyer/contrats', '/loyer/echeances', '/loyer/biens/{id}', '/loyer/contrats/{id}', '/loyer/echeances/{id}'] as $legacy) {
            $router->get($legacy, [$immoController, 'legacy']);
        }
        $router->post('/webhooks/naboopay', [$webhookController, 'nabooPay']);

        return new Kernel($router, new ErrorHandler($logger, $views), [
            new SessionMiddleware($session),
            new CsrfMiddleware($csrf),
            new AuthMiddleware($auth),
            new TenantMiddleware($auth, new TenantRepository(Connection::make($config['db'])), $tenantContext, $saas),
        ], new SecurityHeaders());
    }
}
