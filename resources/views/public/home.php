<?php

declare(strict_types=1);
?>
<section class="home-hero">
    <div class="container-wide">
        <div class="home-hero-grid">
            <div class="home-hero-copy">
                <p class="eyebrow eyebrow-gold"><?= $e($page['eyebrow']) ?></p>
                <h1><?= $e($page['heading']) ?></h1>
                <p class="hero-lead"><?= $e($page['summary']) ?></p>
                <div class="hero-actions">
                    <a class="btn btn-gold btn-lg" href="/register">Become a Member</a>
                    <a class="btn btn-outline-light btn-lg" href="/programmes">Explore Programmes</a>
                </div>
                <a class="hero-verify-link" href="/verify">
                    <span class="verify-icon" aria-hidden="true">✓</span>
                    Verify Membership / Certificate
                    <span aria-hidden="true">→</span>
                </a>
            </div>
            <div class="home-hero-media">
                <picture>
                    <source srcset="/assets/images/aims-professionals-hero.webp" type="image/webp">
                    <img src="/assets/images/aims-professionals-hero.jpg" width="1600" height="840" alt="Professionals collaborating in a contemporary meeting space" fetchpriority="high" decoding="async">
                </picture>
                <div class="hero-media-caption">
                    <span>Knowledge</span>
                    <span>Standards</span>
                    <span>Connection</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section section-intro">
    <div class="container-narrow">
        <div class="section-heading centered">
            <p class="eyebrow">One professional platform</p>
            <h2>Built around the journeys that matter.</h2>
            <p>The AIMS Nigeria platform is structured to make professional information easier to find today while preparing secure, accountable services for future stages.</p>
        </div>
        <div class="pillar-grid">
            <article class="pillar-card">
                <span class="card-number">01</span>
                <h3>Professional membership</h3>
                <p>Understand the available membership grades and the planned application journey.</p>
                <a class="text-link" href="/membership">Explore membership <span aria-hidden="true">→</span></a>
            </article>
            <article class="pillar-card">
                <span class="card-number">02</span>
                <h3>Learning pathways</h3>
                <p>See the programme types and professional areas planned for the platform.</p>
                <a class="text-link" href="/programmes">View programmes <span aria-hidden="true">→</span></a>
            </article>
            <article class="pillar-card">
                <span class="card-number">03</span>
                <h3>Trusted verification</h3>
                <p>Use one clear entry point for future membership and certificate verification.</p>
                <a class="text-link" href="/verify">Go to verification <span aria-hidden="true">→</span></a>
            </article>
        </div>
    </div>
</section>

<section class="section section-warm">
    <div class="container-wide">
        <div class="split-heading">
            <div>
                <p class="eyebrow">Membership grades</p>
                <h2>Progress with a grade that reflects your professional standing.</h2>
            </div>
            <p>Grade abbreviations and detailed eligibility will be published only after formal confirmation. The platform will keep them configurable rather than embedding conflicting versions.</p>
        </div>
        <div class="grade-grid">
            <?php foreach ($membershipGrades as $index => $grade): ?>
                <article class="grade-card">
                    <span class="grade-index"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <h3><?= $e($grade) ?></h3>
                    <p>Eligibility, benefits and approved fees will appear here when configured.</p>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="section-action">
            <a class="btn btn-navy" href="/membership">Understand Membership</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container-wide">
        <div class="section-heading">
            <p class="eyebrow">Professional areas</p>
            <h2>Disciplines connected by responsible practice.</h2>
            <p>Initial areas are presented as a flexible directory that can grow with the association.</p>
        </div>
        <div class="area-grid compact-area-grid">
            <?php foreach ($professionalAreas as $index => $area): ?>
                <a class="area-card" href="/professional-areas/<?= rawurlencode($area['slug']) ?>">
                    <span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <strong><?= $e($area['name']) ?></strong>
                    <span class="area-arrow" aria-hidden="true">↗</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section trust-section">
    <div class="container-wide">
        <div class="trust-panel">
            <div>
                <p class="eyebrow eyebrow-gold">Designed for trust</p>
                <h2>Clear information now. Secure professional services next.</h2>
                <p>The public website and secure account foundation now provide a dependable starting point, while membership applications and transactions remain reserved for their approved implementation stages.</p>
            </div>
            <div class="trust-list" role="list">
                <div role="listitem"><span>✓</span><p><strong>Public-first clarity</strong><br>Simple routes, consistent navigation and purposeful content.</p></div>
                <div role="listitem"><span>✓</span><p><strong>Responsible data</strong><br>No invented people, events, fees or contact information.</p></div>
                <div role="listitem"><span>✓</span><p><strong>Accessible by design</strong><br>Semantic structure, visible focus and mobile-first layouts.</p></div>
            </div>
        </div>
    </div>
</section>

<section class="section section-updates">
    <div class="container-wide">
        <div class="split-heading align-end">
            <div>
                <p class="eyebrow">Stay informed</p>
                <h2>Official updates, when they are ready.</h2>
            </div>
            <a class="text-link" href="/news">Visit the news room <span aria-hidden="true">→</span></a>
        </div>
        <div class="update-grid">
            <article class="update-card update-card-featured">
                <span class="status-pill">Events</span>
                <h3>Upcoming activities will appear in the official event calendar.</h3>
                <p>No event dates have been invented for this launch.</p>
                <a class="text-link" href="/events">Explore events <span aria-hidden="true">→</span></a>
            </article>
            <article class="update-card">
                <span class="status-pill">Resources</span>
                <h3>A structured home for professional publications and downloads.</h3>
                <p>Only reviewed resources will be made available.</p>
                <a class="text-link" href="/resources">Browse resources <span aria-hidden="true">→</span></a>
            </article>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="container-narrow cta-band-inner">
        <div>
            <p class="eyebrow eyebrow-gold">Take the next step</p>
            <h2>Explore your place in the AIMS Nigeria platform.</h2>
        </div>
        <div class="cta-band-actions">
            <a class="btn btn-gold" href="/register">Become a Member</a>
            <a class="btn btn-outline-light" href="/contact">Contact AIMS Nigeria</a>
        </div>
    </div>
</section>
