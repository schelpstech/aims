<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\Security;
use App\Services\Leadership\LeadershipDirectoryInterface;
use App\Services\Programmes\ProgrammeDirectoryInterface;
use App\Services\PublicSite\PublicContent;
use App\View\View;

final class PublicController extends Controller
{
    public function __construct(
        View $view,
        Csrf $csrf,
        private readonly PublicContent $content,
        private readonly ?LeadershipDirectoryInterface $leadershipDirectory = null,
        private readonly ?ProgrammeDirectoryInterface $programmeDirectory = null,
    ) {
        parent::__construct($view, $csrf);
    }

    public function home(Request $request): Response
    {
        $areas = $this->programmeDirectory?->areas() ?? ['areas' => []];
        return $this->page('public.home', 'home', $request, [
            'membershipGrades' => $this->content->collection('membership_grades'),
            'professionalAreas' => $areas['areas'],
        ]);
    }

    public function about(Request $request): Response
    {
        return $this->page('public.about', 'about', $request);
    }

    public function vision(Request $request): Response
    {
        return $this->page('public.vision', 'vision', $request);
    }

    public function leadership(Request $request): Response
    {
        $directory = $this->leadershipDirectory?->directory() ?? [
            'available' => false,
            'groups' => array_map(
                static fn (string $name): array => ['name' => $name, 'slug' => '', 'description' => null, 'members' => []],
                $this->content->collection('leadership_groups'),
            ),
        ];

        return $this->page('public.leadership', 'leadership', $request, [
            'leadershipDirectoryAvailable' => $directory['available'],
            'leadershipGroups' => $directory['groups'],
        ]);
    }

    public function membership(Request $request): Response
    {
        return $this->page('public.membership', 'membership', $request, [
            'membershipGrades' => $this->content->collection('membership_grades'),
        ]);
    }

    public function programmes(Request $request): Response
    {
        $catalogue = $this->programmeDirectory?->catalogue() ?? ['available' => false, 'types' => [], 'programmes' => []];
        return $this->page('public.programmes', 'programmes', $request, [
            'catalogueAvailable' => $catalogue['available'],
            'programmeTypes' => $catalogue['types'],
            'programmes' => $catalogue['programmes'],
        ]);
    }

    public function professionalAreas(Request $request): Response
    {
        $directory = $this->programmeDirectory?->areas() ?? ['available' => false, 'areas' => []];
        return $this->page('public.professional-areas', 'areas', $request, [
            'directoryAvailable' => $directory['available'],
            'professionalAreas' => $directory['areas'],
        ]);
    }

    public function programme(Request $request): Response
    {
        $programme = $this->programmeDirectory?->programme((string) $request->route('programme', ''));
        if ($programme === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }
        return $this->page('public.programme', 'programmes', $request, ['programme' => $programme, 'page' => [
            'title' => $programme['name'], 'meta_title' => $programme['name'] . ' | AIMS Nigeria',
            'description' => $programme['description'] ?: 'A professional programme from AIMS Nigeria.',
            'eyebrow' => $programme['type']['name'], 'heading' => $programme['name'],
            'summary' => $programme['description'] ?: 'Verified programme information from the official catalogue.',
        ]]);
    }

    public function professionalArea(Request $request): Response
    {
        $area = $this->programmeDirectory?->area((string) $request->route('area', ''));
        if ($area === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }
        return $this->page('public.professional-area', 'areas', $request, ['professionalArea' => $area, 'page' => [
            'title' => $area['name'], 'meta_title' => $area['name'] . ' | AIMS Nigeria',
            'description' => $area['description'] ?: 'A professional area represented by AIMS Nigeria.',
            'eyebrow' => 'Professional area', 'heading' => $area['name'],
            'summary' => $area['description'] ?: 'Explore verified programmes and coordinators connected with this professional area.',
        ]]);
    }

    public function events(Request $request): Response
    {
        return $this->page('public.events', 'events', $request, [
            'eventTypes' => $this->content->collection('event_types'),
        ]);
    }

    public function resources(Request $request): Response
    {
        return $this->page('public.resources', 'resources', $request);
    }

    public function news(Request $request): Response
    {
        return $this->page('public.news', 'news', $request);
    }

    public function contact(Request $request): Response
    {
        return $this->page('public.contact', 'contact', $request);
    }

    public function verify(Request $request): Response
    {
        return $this->page('public.verify', 'verify', $request);
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, string $pageKey, Request $request, array $data = []): Response
    {
        return $this->view($template, array_merge([
            'site' => $this->content->site(),
            'navigation' => $this->content->navigation(),
            'page' => $this->content->page($pageKey),
            'activePage' => $pageKey,
            'requestPath' => $request->path(),
            'e' => [Security::class, 'escape'],
        ], $data), 'layouts.public');
    }
}
