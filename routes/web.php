<?php

declare(strict_types=1);

use App\Controllers\Auth\AuthController;
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
use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;
use App\Middleware\AuthenticateMiddleware;
use App\Middleware\MemberAccessMiddleware;

return static function (
    Router $router,
    PublicController $public,
    ?AuthController $auth = null,
    ?AuthenticateMiddleware $authenticate = null,
    ?MembershipApplicationController $membership = null,
    ?MembershipAdminController $membershipAdmin = null,
    array $memberPermissions = [],
    ?MemberPortalController $memberPortal = null,
    ?MemberAccessMiddleware $memberAccess = null,
    ?ProgrammeAdminController $programmeAdmin = null,
    array $programmePermissions = [],
    ?ProgrammeApplicationController $programmeApplications = null,
    ?ProgrammeApplicationAdminController $programmeApplicationAdmin = null,
    ?EventController $events = null,
    ?EventAdminController $eventAdmin = null,
    ?\App\Middleware\AuthorizeMiddleware $eventManage = null,
    ?PaymentController $payments = null,
    ?MembershipRenewalController $renewals = null,
    ?\App\Middleware\AuthorizeMiddleware $renewalOverride = null,
    ?\App\Middleware\AuthorizeMiddleware $renewalPolicy = null,
    ?CertificateController $certificates = null,
    ?\App\Middleware\AuthorizeMiddleware $certificateIssue = null,
    ?\App\Middleware\AuthorizeMiddleware $certificateRevoke = null,
    ?\App\Middleware\AuthorizeMiddleware $certificateTypeManage = null,
    ?CmsController $cms = null,
    ?\App\Middleware\AuthorizeMiddleware $cmsEdit = null,
    ?ReportingController $reporting = null,
    ?\App\Middleware\AuthorizeMiddleware $reportView = null,
    ?\App\Middleware\AuthorizeMiddleware $reportExport = null,
): void {
    $router->get('/health', static function (Request $request): Response {
        return Response::json([
            'status' => 'ok',
            'service' => 'AIMS Nigeria Platform',
        ])->withHeader('Cache-Control', 'no-store');
    });

    $router->get('/', [$public, 'home']);
    $router->get('/about', [$public, 'about']);
    $router->get('/vision-mission', [$public, 'vision']);
    $router->get('/leadership', [$public, 'leadership']);
    $router->get('/membership', [$public, 'membership']);
    $router->get('/programmes', [$public, 'programmes']);
    $router->get('/programmes/{programme}', [$public, 'programme']);
    $router->get('/professional-areas', [$public, 'professionalAreas']);
    $router->get('/professional-areas/{area}', [$public, 'professionalArea']);
    $router->get('/events', $events === null ? [$public, 'events'] : [$events, 'index']);
    if ($events !== null) {
        $router->get('/events/{event}', [$events, 'show']);
        $router->post('/events/{event}/register', [$events, 'publicRegister']);
    }
    $router->get('/resources', $cms===null?[$public,'resources']:[$cms,'resources']);
    $router->get('/news', $cms===null?[$public,'news']:[$cms,'news']);
    if($cms!==null){$router->get('/news/{slug}',[$cms,'newsPost']);$router->get('/announcements',[$cms,'announcements']);$router->get('/announcements/{slug}',[$cms,'announcementPost']);$router->get('/resources/{slug}',[$cms,'resourcePost']);$router->get('/downloads',[$cms,'downloads']);$router->get('/downloads/{slug}',[$cms,'download']);$router->get('/faqs',[$cms,'faqs']);$router->get('/pages/{slug}',[$cms,'page']);$router->get('/media/{media}',[$cms,'media']);}
    $router->get('/contact', [$public, 'contact']);
    $router->get('/verify', [$public, 'verify']);
    if($certificates!==null){$router->post('/verify',[$certificates,'verify']);$router->get('/verify/qr',[$certificates,'qr']);}

    if ($auth === null || $authenticate === null) {
        return;
    }

    if ($payments !== null) {
        $router->get('/payments/callback', [$payments, 'callback']);
        $router->post('/payments/webhook', [$payments, 'webhook']);
    }
    if ($renewals !== null) {
        $router->get('/account/membership-renewal', [$renewals, 'index'], [$authenticate]);
        $router->post('/account/membership-renewal', [$renewals, 'generate'], [$authenticate]);
    }
    if($certificates!==null){$router->get('/account/certificates',[$certificates,'member'],[$authenticate]);}

    $router->get('/login', [$auth, 'showLogin']);
    $router->post('/login', [$auth, 'login']);
    $router->get('/register', [$auth, 'showRegister']);
    $router->post('/register', [$auth, 'register']);
    $router->post('/account/create', [$auth, 'register']);
    $router->get('/forgot-password', [$auth, 'showForgotPassword']);
    $router->post('/forgot-password', [$auth, 'forgotPassword']);
    $router->get('/reset-password', [$auth, 'showResetPassword']);
    $router->post('/reset-password', [$auth, 'resetPassword']);
    $router->get('/verify-email', [$auth, 'showVerifyEmail']);
    $router->post('/verify-email', [$auth, 'verifyEmail']);
    $router->get('/account', [$auth, 'account'], [$authenticate]);
    $router->post('/logout', [$auth, 'logout'], [$authenticate]);
    if ($payments !== null) {
        $router->get('/account/invoices', [$payments, 'index'], [$authenticate]);
        $router->post('/account/invoices/{invoice}/pay', [$payments, 'initialize'], [$authenticate]);
    }

    if ($membership !== null) {
        $router->get('/account/membership-application', [$membership, 'show'], [$authenticate]);
        $router->post('/account/membership-application/draft', [$membership, 'saveDraft'], [$authenticate]);
        $router->post('/account/membership-application/documents', [$membership, 'uploadDocument'], [$authenticate]);
        $router->post('/account/membership-application/submit', [$membership, 'submit'], [$authenticate]);
        $router->post('/account/membership-application/cancel', [$membership, 'cancel'], [$authenticate]);
    }

    if ($programmeApplications !== null) {
        $router->get('/account/programme-applications', [$programmeApplications, 'index'], [$authenticate]);
        $router->post('/account/programme-applications/draft', [$programmeApplications, 'saveDraft'], [$authenticate]);
        $router->post('/account/programme-applications/submit', [$programmeApplications, 'submit'], [$authenticate]);
        $router->post('/account/programme-applications/{application}/withdraw', [$programmeApplications, 'withdraw'], [$authenticate]);
    }
    if ($events !== null) {
        $router->get('/account/event-registrations',[$events,'memberIndex'],[$authenticate]);
        $router->post('/account/events/{event}/register',[$events,'memberRegister'],[$authenticate]);
    }

    if ($membershipAdmin !== null && isset(
        $memberPermissions['view'],
        $memberPermissions['review'],
        $memberPermissions['approve'],
        $memberPermissions['reject'],
        $memberPermissions['query'],
    )) {
        $router->get('/admin/membership/applications', [$membershipAdmin, 'index'], [$authenticate, $memberPermissions['view']]);
        $router->get('/admin/membership/applications/{application}', [$membershipAdmin, 'show'], [$authenticate, $memberPermissions['view']]);
        $router->get('/admin/membership/documents/{document}', [$membershipAdmin, 'document'], [$authenticate, $memberPermissions['view']]);
        $router->post('/admin/membership/applications/{application}/review', [$membershipAdmin, 'review'], [$authenticate, $memberPermissions['review']]);
        $router->post('/admin/membership/applications/{application}/query', [$membershipAdmin, 'query'], [$authenticate, $memberPermissions['query']]);
        $router->post('/admin/membership/applications/{application}/approve', [$membershipAdmin, 'approve'], [$authenticate, $memberPermissions['approve']]);
        $router->post('/admin/membership/applications/{application}/reject', [$membershipAdmin, 'reject'], [$authenticate, $memberPermissions['reject']]);
    }

    if ($memberPortal !== null && $memberAccess !== null) {
        $memberGuards = [$authenticate, $memberAccess];
        $router->get('/portal', [$memberPortal, 'dashboard'], $memberGuards);
        $router->get('/portal/profile', [$memberPortal, 'profile'], $memberGuards);
        $router->post('/portal/profile', [$memberPortal, 'updateProfile'], $memberGuards);
        $router->get('/portal/{section}', [$memberPortal, 'section'], $memberGuards);
    }

    if ($programmeAdmin !== null && isset($programmePermissions['view'], $programmePermissions['create'], $programmePermissions['update'], $programmePermissions['delete'], $programmePermissions['assign_coordinators'])) {
        $router->get('/admin/programmes', [$programmeAdmin, 'index'], [$authenticate, $programmePermissions['view']]);
        $router->post('/admin/programmes', [$programmeAdmin, 'createProgramme'], [$authenticate, $programmePermissions['create']]);
        $router->post('/admin/programmes/coordinators', [$programmeAdmin, 'assignCoordinator'], [$authenticate, $programmePermissions['assign_coordinators']]);
        $router->post('/admin/programmes/coordinators/{assignment}/remove', [$programmeAdmin, 'removeCoordinator'], [$authenticate, $programmePermissions['assign_coordinators']]);
        $router->post('/admin/programmes/{programme}', [$programmeAdmin, 'updateProgramme'], [$authenticate, $programmePermissions['update']]);
        $router->post('/admin/programmes/{programme}/archive', [$programmeAdmin, 'archiveProgramme'], [$authenticate, $programmePermissions['delete']]);
        $router->post('/admin/professional-areas', [$programmeAdmin, 'createArea'], [$authenticate, $programmePermissions['create']]);
        $router->post('/admin/professional-areas/{area}', [$programmeAdmin, 'updateArea'], [$authenticate, $programmePermissions['update']]);
        $router->post('/admin/professional-areas/{area}/archive', [$programmeAdmin, 'archiveArea'], [$authenticate, $programmePermissions['delete']]);
    }

    if ($programmeApplicationAdmin !== null && isset($programmePermissions['application_view'], $programmePermissions['application_review'], $programmePermissions['application_approve'], $programmePermissions['application_reject'], $programmePermissions['enrol'])) {
        $router->get('/admin/programme-applications',[$programmeApplicationAdmin,'index'],[$authenticate,$programmePermissions['application_view']]);
        $router->get('/admin/programme-applications/{application}',[$programmeApplicationAdmin,'show'],[$authenticate,$programmePermissions['application_view']]);
        $router->post('/admin/programme-applications/{application}/review',[$programmeApplicationAdmin,'review'],[$authenticate,$programmePermissions['application_review']]);
        $router->post('/admin/programme-applications/{application}/approve',[$programmeApplicationAdmin,'approve'],[$authenticate,$programmePermissions['application_approve']]);
        $router->post('/admin/programme-applications/{application}/reject',[$programmeApplicationAdmin,'reject'],[$authenticate,$programmePermissions['application_reject']]);
        $router->post('/admin/programme-applications/{application}/enrol',[$programmeApplicationAdmin,'enrol'],[$authenticate,$programmePermissions['enrol']]);
        $router->post('/admin/programme-applications/{application}/complete',[$programmeApplicationAdmin,'complete'],[$authenticate,$programmePermissions['enrol']]);
        $router->post('/admin/programme-applications/{application}/withdraw-enrolment',[$programmeApplicationAdmin,'withdraw'],[$authenticate,$programmePermissions['enrol']]);
    }
    if($eventAdmin!==null&&$eventManage!==null){$guard=[$authenticate,$eventManage];$router->get('/admin/events',[$eventAdmin,'index'],$guard);$router->post('/admin/events',[$eventAdmin,'create'],$guard);$router->get('/admin/events/{event}/attendees',[$eventAdmin,'attendees'],$guard);$router->post('/admin/events/{event}',[$eventAdmin,'update'],$guard);$router->post('/admin/events/{event}/cancel',[$eventAdmin,'cancel'],$guard);$router->post('/admin/events/{event}/attendees/{registration}/attend',[$eventAdmin,'attend'],$guard);$router->post('/admin/events/{event}/attendees/{registration}/cancel',[$eventAdmin,'cancelRegistration'],$guard);}
    if($renewals!==null&&$renewalOverride!==null){$router->get('/admin/membership-renewals',[$renewals,'admin'],[$authenticate]);$router->post('/admin/membership-renewals/{renewal}/override',[$renewals,'override'],[$authenticate,$renewalOverride]);}
    if($renewals!==null&&$renewalPolicy!==null){$guard=[$authenticate,$renewalPolicy];$router->post('/admin/membership-renewal-policies',[$renewals,'savePolicy'],$guard);$router->post('/admin/membership-renewal-policies/{policy}',[$renewals,'savePolicy'],$guard);}
    if($certificates!==null){$router->get('/admin/certificates',[$certificates,'admin'],[$authenticate]);if($certificateIssue!==null)$router->post('/admin/certificates',[$certificates,'issue'],[$authenticate,$certificateIssue]);if($certificateRevoke!==null)$router->post('/admin/certificates/{certificate}/revoke',[$certificates,'revoke'],[$authenticate,$certificateRevoke]);if($certificateTypeManage!==null){$router->post('/admin/certificate-types',[$certificates,'saveType'],[$authenticate,$certificateTypeManage]);$router->post('/admin/certificate-types/{type}',[$certificates,'saveType'],[$authenticate,$certificateTypeManage]);}}
    if($cms!==null&&$cmsEdit!==null){$guard=[$authenticate,$cmsEdit];$router->get('/admin/cms',[$cms,'admin'],$guard);$router->post('/admin/cms/pages',[$cms,'savePage'],$guard);$router->post('/admin/cms/pages/{page}',[$cms,'savePage'],$guard);$router->post('/admin/cms/posts',[$cms,'savePost'],$guard);$router->post('/admin/cms/posts/{post}',[$cms,'savePost'],$guard);$router->post('/admin/cms/faqs',[$cms,'saveFaq'],$guard);$router->post('/admin/cms/faqs/{faq}',[$cms,'saveFaq'],$guard);$router->post('/admin/cms/media',[$cms,'upload'],$guard);$router->post('/admin/cms/media/{media}/status',[$cms,'mediaStatus'],$guard);$router->post('/admin/cms/downloads',[$cms,'saveDownload'],$guard);$router->post('/admin/cms/downloads/{download}',[$cms,'saveDownload'],$guard);}
    if($reporting!==null&&$reportView!==null){$router->get('/admin/reports',[$reporting,'index'],[$authenticate,$reportView]);if($reportExport!==null)$router->get('/admin/reports/export',[$reporting,'export'],[$authenticate,$reportView,$reportExport]);}
};
