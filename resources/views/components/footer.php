<?php

declare(strict_types=1);
?>
<footer class="site-footer">
    <div class="container-wide">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="footer-logo" href="/" aria-label="AIMS Nigeria home">
                    <span class="brand-mark brand-mark-light" aria-hidden="true">A</span>
                    <span><?= $e($site['short_name']) ?></span>
                </a>
                <p>A professional digital platform for membership, learning, public information and trusted verification services.</p>
                <a class="footer-verify" href="/verify">
                    <span aria-hidden="true">✓</span>
                    Verify Membership / Certificate
                </a>
            </div>

            <div>
                <h2 class="footer-heading">Explore</h2>
                <ul class="footer-links">
                    <li><a href="/about">About</a></li>
                    <li><a href="/vision-mission">Vision &amp; Mission</a></li>
                    <li><a href="/leadership">Leadership</a></li>
                    <li><a href="/professional-areas">Professional Areas</a></li>
                </ul>
            </div>

            <div>
                <h2 class="footer-heading">Opportunities</h2>
                <ul class="footer-links">
                    <li><a href="/membership">Membership</a></li>
                    <li><a href="/programmes">Programmes</a></li>
                    <li><a href="/events">Events</a></li>
                    <li><a href="/resources">Resources</a></li>
                </ul>
            </div>

            <div>
                <h2 class="footer-heading">Information</h2>
                <ul class="footer-links">
                    <li><a href="/news">News</a></li>
                    <li><a href="/contact">Contact</a></li>
                    <li><a href="/verify">Public verification</a></li>
                </ul>
                <p class="footer-note">Official contact and registration information will be published only after verification.</p>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= $e($site['name']) ?>.</p>
            <p>Designed for accessibility, clarity and responsible professional service.</p>
        </div>
    </div>
</footer>

