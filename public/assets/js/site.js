(() => {
    'use strict';

    document.documentElement.classList.add('js-enabled');

    const header = document.querySelector('[data-site-header]');
    const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 12);
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    const navigation = document.getElementById('primaryNavigation');
    const navigationToggle = document.querySelector('[aria-controls="primaryNavigation"]');
    if (navigation && navigationToggle) {
        const closeNavigation = () => {
            navigation.classList.remove('show');
            navigationToggle.setAttribute('aria-expanded', 'false');
        };

        navigationToggle.addEventListener('click', () => {
            const opening = !navigation.classList.contains('show');
            navigation.classList.toggle('show', opening);
            navigationToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        });

        navigation.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.getComputedStyle(navigationToggle).display !== 'none') {
                    closeNavigation();
                }
            });
        });

        window.addEventListener('resize', () => {
            if (window.getComputedStyle(navigationToggle).display === 'none') {
                closeNavigation();
            }
        }, { passive: true });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && navigation.classList.contains('show')) {
                closeNavigation();
                navigationToggle.focus();
            }
        });
    }

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            const submitter = event.submitter;
            if (submitter instanceof HTMLElement) {
                submitter.classList.add('is-loading');
                submitter.setAttribute('aria-disabled', 'true');
            }
        });
    });

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting="true"]').forEach((form) => {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            form.querySelectorAll('.is-loading').forEach((button) => {
                button.classList.remove('is-loading');
                button.removeAttribute('aria-disabled');
            });
        });
    });

    const targets = document.querySelectorAll(
        '.pillar-card, .grade-card, .feature-card, .programme-card, .resource-card, .leadership-card, .area-card, .update-card'
    );

    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        targets.forEach((target) => target.classList.add('is-visible'));
        return;
    }

    targets.forEach((target) => target.classList.add('reveal-target'));
    const observer = new IntersectionObserver((entries, currentObserver) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }
            entry.target.classList.add('is-visible');
            currentObserver.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    targets.forEach((target) => observer.observe(target));
})();
