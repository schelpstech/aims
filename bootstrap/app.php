<?php

declare(strict_types=1);

use App\Application;
use App\Auth\AuthService;
use App\Auth\NativeMailTokenDelivery;
use App\Auth\PasswordPolicy;
use App\Auth\PdoAuthRepository;
use App\Authorization\PdoPermissionChecker;
use App\Config\Config;
use App\Config\Environment;
use App\Controllers\Auth\AuthController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Membership\MembershipApplicationController;
use App\Controllers\Membership\MembershipAdminController;
use App\Controllers\MemberPortal\MemberPortalController;
use App\Controllers\Public\PublicController;
use App\Controllers\Programmes\ProgrammeAdminController;
use App\Controllers\Programmes\ProgrammeApplicationController;
use App\Controllers\Programmes\ProgrammeApplicationAdminController;
use App\Controllers\Events\EventController;
use App\Controllers\Events\EventAdminController;
use App\Controllers\Payments\PaymentController;
use App\Controllers\Membership\MembershipRenewalController;
use App\Controllers\Certificates\CertificateController;
use App\Controllers\Cms\CmsController;
use App\Controllers\Reporting\ReportingController;
use App\Database\Connection;
use App\Exceptions\Handler;
use App\Logging\Logger;
use App\Middleware\CsrfMiddleware;
use App\Middleware\AuthenticateMiddleware;
use App\Middleware\AuthorizeMiddleware;
use App\Middleware\MemberAccessMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use App\Repositories\LeadershipRepository;
use App\Repositories\MembershipRepository;
use App\Repositories\MembershipAdminRepository;
use App\Repositories\MemberPortalRepository;
use App\Repositories\ProgrammeRepository;
use App\Repositories\ProgrammeAdminRepository;
use App\Repositories\ProgrammeApplicationRepository;
use App\Repositories\EventRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\MembershipRenewalRepository;
use App\Repositories\CertificateRepository;
use App\Repositories\CmsRepository;
use App\Repositories\ReportingRepository;
use App\Routing\Router;
use App\Security\Csrf;
use App\Security\SessionManager;
use App\Services\Leadership\LeadershipService;
use App\Services\Membership\MembershipService;
use App\Services\Membership\PrivateDocumentStorage;
use App\Services\MembershipAdmin\MembershipAdminService;
use App\Services\MemberPortal\PdoMemberActivitySummary;
use App\Services\MemberPortal\MemberPortalService;
use App\Services\PublicSite\PublicContent;
use App\Services\Programmes\ProgrammeDirectoryService;
use App\Services\ProgrammeAdmin\ProgrammeAdminService;
use App\Services\ProgrammeApplications\ProgrammeApplicationService;
use App\Services\Events\EventService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\UnavailablePaymentGateway;
use App\Services\Renewals\MembershipRenewalService;
use App\Services\Certificates\CertificateService;
use App\Security\CertificateTokenSigner;
use App\Security\SafeHtml;
use App\Services\Cms\CmsService;
use App\Services\Cms\CmsMediaStorage;
use App\Services\Reporting\ReportingService;
use App\Validation\Validator;
use App\View\View;

$basePath = dirname(__DIR__);
$environment = Environment::load($basePath . DIRECTORY_SEPARATOR . '.env');
$config = Config::load($basePath . DIRECTORY_SEPARATOR . 'config', $environment);

$debug = (bool) $config->get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
date_default_timezone_set((string) $config->get('app.timezone', 'Africa/Lagos'));

$logPath = (string) $config->get('logging.path', 'storage/logs/application.log');
if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $logPath)) {
    $logPath = $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logPath);
}

$logger = new Logger($logPath, (string) $config->get('logging.level', 'info'));
$errorHandler = new Handler($logger, $debug);
$errorHandler->register();

$sessionConfig = (array) $config->get('session', []);
$sessionSavePath = (string) ($sessionConfig['save_path'] ?? 'storage/sessions');
if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $sessionSavePath)) {
    $sessionSavePath = $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sessionSavePath);
}
$sessionConfig['save_path'] = $sessionSavePath;

$session = new SessionManager($sessionConfig);
$csrf = new Csrf($session);
$view = new View($basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
$publicContent = new PublicContent($config);
$connection = new Connection($config);
$leadershipService = new LeadershipService(
    new LeadershipRepository($connection),
    $logger,
    (array) $config->get('public.leadership_groups', []),
);
$programmeDirectory = new ProgrammeDirectoryService(new ProgrammeRepository($connection), $logger);
$publicController = new PublicController($view, $csrf, $publicContent, $leadershipService, $programmeDirectory);
$authRepository = new PdoAuthRepository($connection);
$authService = new AuthService(
    repository: $authRepository,
    session: $session,
    csrf: $csrf,
    passwordPolicy: new PasswordPolicy(
        (int) $config->get('auth.password_min_length', 12),
        (int) $config->get('auth.password_max_length', 4096),
    ),
    delivery: new NativeMailTokenDelivery(
        (array) $config->get('mail', []),
        (string) $config->get('app.url', ''),
        $logger,
        $connection,
    ),
    config: (array) $config->get('auth', []),
    sessionLifetimeMinutes: (int) ($sessionConfig['lifetime'] ?? 120),
);
$permissionChecker = new PdoPermissionChecker($connection);
$memberPortalService = new MemberPortalService(
    new MemberPortalRepository($connection),
    new PdoMemberActivitySummary($connection),
    new Validator(),
);
$authController = new AuthController(
    $view,
    $csrf,
    $authService,
    $session,
    $publicContent,
    $permissionChecker,
    $memberPortalService,
);
$authenticate = new AuthenticateMiddleware($authService);
$membershipConfig = (array) $config->get('membership.documents', []);
$membershipStoragePath = (string) ($membershipConfig['path'] ?? 'storage/private/membership-documents');
if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $membershipStoragePath)) {
    $membershipStoragePath = $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $membershipStoragePath);
}
$normalizedMembershipStorage = strtolower(str_replace('\\', '/', rtrim($membershipStoragePath, '/\\')));
$normalizedPublicPath = strtolower(str_replace('\\', '/', $basePath . DIRECTORY_SEPARATOR . 'public'));
if ($normalizedMembershipStorage === $normalizedPublicPath || str_starts_with($normalizedMembershipStorage, $normalizedPublicPath . '/')) {
    throw new RuntimeException('Membership document storage must be outside the public document root.');
}
$documentStorage = new PrivateDocumentStorage(
    $membershipStoragePath,
    max(1, (int) ($membershipConfig['max_bytes'] ?? 5 * 1024 * 1024)),
    (array) ($membershipConfig['allowed_mime_types'] ?? []),
);
$membershipService = new MembershipService(
    new MembershipRepository($connection),
    $documentStorage,
    new Validator(),
);
$membershipController = new MembershipApplicationController(
    $view,
    $csrf,
    $membershipService,
    $session,
    $publicContent,
);
$membershipAdminService = new MembershipAdminService(
    new MembershipAdminRepository($connection),
    $permissionChecker,
    $documentStorage,
    (string) $config->get('membership.number_prefix', 'AIMS'),
);
$membershipAdminController = new MembershipAdminController(
    $view,
    $csrf,
    $membershipAdminService,
    $permissionChecker,
    $session,
    $publicContent,
);
$memberPortalController = new MemberPortalController(
    $view,
    $csrf,
    $memberPortalService,
    $session,
    $publicContent,
);
$memberAccess = new MemberAccessMiddleware($memberPortalService);
$programmeAdminController = new ProgrammeAdminController(
    $view,
    $csrf,
    new ProgrammeAdminService(new ProgrammeAdminRepository($connection), $permissionChecker),
    $permissionChecker,
    $session,
    $publicContent,
);
$programmeApplicationService = new ProgrammeApplicationService(
    new ProgrammeApplicationRepository($connection),
    $permissionChecker,
    new Validator(),
    (string) $config->get('programmes.enrolment_number_prefix', 'AIMS-PRG'),
);
$programmeApplicationController = new ProgrammeApplicationController($view,$csrf,$programmeApplicationService,$session,$publicContent);
$programmeApplicationAdminController = new ProgrammeApplicationAdminController($view,$csrf,$programmeApplicationService,$session,$publicContent);
$eventService = new EventService(new EventRepository($connection),$permissionChecker,$logger);
$eventController = new EventController($view,$csrf,$eventService,$session,$publicContent);
$eventAdminController = new EventAdminController($view,$csrf,$eventService,$session,$publicContent);
$renewalService = new MembershipRenewalService(new MembershipRenewalRepository($connection), $permissionChecker);
$renewalController = new MembershipRenewalController($view, $csrf, $renewalService, $session, $publicContent);
$paymentGatewayName = (string) $config->get('payments.gateway', 'disabled');
$paymentService = new PaymentService(
    new PaymentRepository($connection),
    new UnavailablePaymentGateway($paymentGatewayName),
    (string) $config->get('payments.callback_url', ''),
    $renewalService,
);
$paymentController = new PaymentController($view, $csrf, $paymentService, $session, $publicContent);
$certificateService = new CertificateService(new CertificateRepository($connection),$permissionChecker,new CertificateTokenSigner((string)$config->get('certificates.signing_key','')),(string)$config->get('certificates.number_prefix','AIMS-CERT'),(string)$config->get('certificates.verification_url',''));
$certificateController = new CertificateController($view,$csrf,$certificateService,$session,$publicContent);
$cmsPath=(string)$config->get('cms.media_path','storage/private/cms-media');
if(!preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/',$cmsPath))$cmsPath=$basePath.DIRECTORY_SEPARATOR.str_replace(['/','\\'],DIRECTORY_SEPARATOR,$cmsPath);
$normalizedCms=strtolower(str_replace('\\','/',rtrim($cmsPath,'/\\')));if($normalizedCms===$normalizedPublicPath||str_starts_with($normalizedCms,$normalizedPublicPath.'/'))throw new RuntimeException('CMS media storage must be outside the public document root.');
$cmsService=new CmsService(new CmsRepository($connection),$permissionChecker,new SafeHtml(),new CmsMediaStorage($cmsPath,max(1,(int)$config->get('cms.max_upload_bytes',10485760))));
$cmsController=new CmsController($view,$csrf,$cmsService,$session,$publicContent);
$reportingController=new ReportingController($view,$csrf,new ReportingService(new ReportingRepository($connection),$permissionChecker),$publicContent);
$adminDashboardController = new AdminDashboardController($view, $csrf, $permissionChecker, $publicContent);
$memberPermissionMiddleware = [];
foreach (['view', 'review', 'approve', 'reject', 'query'] as $permissionAction) {
    $memberPermissionMiddleware[$permissionAction] = new AuthorizeMiddleware(
        $permissionChecker,
        'member.' . $permissionAction,
    );
}
$programmePermissionMiddleware = [];
foreach (['view', 'create', 'update', 'delete', 'assign_coordinators', 'application_view', 'application_review', 'application_approve', 'application_reject', 'enrol'] as $permissionAction) {
    $programmePermissionMiddleware[$permissionAction] = new AuthorizeMiddleware($permissionChecker, 'programme.' . $permissionAction);
}
$eventManageMiddleware = new AuthorizeMiddleware($permissionChecker,'event.manage');
$renewalOverrideMiddleware = new AuthorizeMiddleware($permissionChecker,'member.renewal_override');
$renewalPolicyMiddleware = new AuthorizeMiddleware($permissionChecker,'member.renewal_policy');
$certificateIssueMiddleware = new AuthorizeMiddleware($permissionChecker,'certificate.issue');
$certificateRevokeMiddleware = new AuthorizeMiddleware($permissionChecker,'certificate.revoke');
$certificateTypeMiddleware = new AuthorizeMiddleware($permissionChecker,'certificate.type_manage');
$cmsEditMiddleware = new AuthorizeMiddleware($permissionChecker,'cms.edit');
$reportViewMiddleware = new AuthorizeMiddleware($permissionChecker,'report.view');
$reportExportMiddleware = new AuthorizeMiddleware($permissionChecker,'report.export');
$router = new Router();

$router->middleware(new SecurityHeadersMiddleware(
    (array) $config->get('security.headers', []),
    (array) $config->get('security.https_headers', []),
));
$router->middleware(new SessionMiddleware($session, ['/health']));
$router->middleware(new CsrfMiddleware($csrf, ['/payments/webhook']));

$registerRoutes = require $basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$registerRoutes(
    $router,
    $publicController,
    $authController,
    $authenticate,
    $membershipController,
    $membershipAdminController,
    $memberPermissionMiddleware,
    $memberPortalController,
    $memberAccess,
    $programmeAdminController,
    $programmePermissionMiddleware,
    $programmeApplicationController,
    $programmeApplicationAdminController,
    $eventController,
    $eventAdminController,
    $eventManageMiddleware,
    $paymentController,
    $renewalController,
    $renewalOverrideMiddleware,
    $renewalPolicyMiddleware,
    $certificateController,
    $certificateIssueMiddleware,
    $certificateRevokeMiddleware,
    $certificateTypeMiddleware,
    $cmsController,
    $cmsEditMiddleware,
    $reportingController,
    $reportViewMiddleware,
    $reportExportMiddleware,
    $adminDashboardController,
);

return new Application(
    router: $router,
    errorHandler: $errorHandler,
    view: $view,
    config: $config,
);
