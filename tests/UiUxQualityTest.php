<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $root = dirname(__DIR__);
    $css = (string) file_get_contents($root . '/public/assets/css/site.css');
    $javascript = (string) file_get_contents($root . '/public/assets/js/site.js');

    $assert(substr_count($css, '{') === substr_count($css, '}'), 'The application stylesheet has unbalanced blocks.');
    foreach (['.section-space', '.notice,', '.form-error,', '.card-grid', '.cta-row', '.table-responsive,'] as $selector) {
        $assert(str_contains($css, $selector), "Shared UI treatment is missing for {$selector}.");
    }
    foreach (['@media (max-width: 35.99rem)', '@media (min-width: 36rem)', '@media (min-width: 62rem)', '@media (min-width: 1040px)'] as $breakpoint) {
        $assert(str_contains($css, $breakpoint), "Responsive coverage is missing for {$breakpoint}.");
    }
    $assert(str_contains($css, 'overflow-x: auto') && str_contains($css, 'overscroll-behavior-inline: contain'), 'Wide tables do not have contained horizontal scrolling.');
    $assert(str_contains($css, ':focus-visible') && str_contains($css, ':where(input, select, textarea):focus'), 'Keyboard and form focus indicators are incomplete.');
    $assert(str_contains($css, 'aspect-ratio: 16 / 9') && str_contains($css, 'object-fit: cover'), 'Catalogue images are not protected from distortion.');
    $assert(str_contains($javascript, "event.key === 'Escape'"), 'The mobile navigation cannot be dismissed from the keyboard.');
    $assert(str_contains($javascript, "form.setAttribute('aria-busy', 'true')"), 'Form loading feedback is missing.');
    $assert(str_contains($javascript, "event.preventDefault()"), 'Duplicate form submissions are not suppressed.');

    $membershipTable = (string) file_get_contents($root . '/resources/views/membership-admin/index.php');
    $reporting = (string) file_get_contents($root . '/resources/views/reporting/index.php');
    $assert(str_contains($membershipTable, 'role="region" aria-label="Membership applications table" tabindex="0"'), 'The membership table is not keyboard-scrollable and named.');
    $assert(substr_count($reporting, 'class="table-responsive" role="region"') === 2, 'Reporting tables are not keyboard-scrollable regions.');

    foreach (glob($root . '/resources/views/**/*.php') ?: [] as $view) {
        $markup = (string) file_get_contents($view);
        $assert(preg_match('/<div class="notice"(?![^>]*role=)/', $markup) !== 1, basename($view) . ' has a success state without status semantics.');
        $assert(preg_match('/<div class="form-error"(?![^>]*role=)/', $markup) !== 1, basename($view) . ' has an error state without alert semantics.');
    }

    echo "UI/UX quality checks passed: responsive foundations, overflow containment, accessible states, focus treatment, image sizing, and loading feedback.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'UI/UX quality check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
