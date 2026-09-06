<?php

declare(strict_types=1);

echo $view->render('components.page-hero', compact('page', 'e'));
?>
<section class="section" id="membership-interest">
    <div class="container-wide">
        <div class="split-heading">
            <div>
                <p class="eyebrow">Initial membership grades</p>
                <h2>Three pathways, configured for accuracy.</h2>
            </div>
            <p>Official abbreviations, eligibility, benefits and fees have not been assumed. They will be maintained as approved membership-grade data.</p>
        </div>
        <div class="grade-grid">
            <?php foreach ($membershipGrades as $index => $grade): ?>
                <article class="grade-card grade-card-detailed">
                    <span class="grade-index"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <h2><?= $e($grade) ?></h2>
                    <dl>
                        <div><dt>Eligibility</dt><dd>Awaiting approved criteria</dd></div>
                        <div><dt>Benefits</dt><dd>Awaiting approved benefits</dd></div>
                        <div><dt>Fees</dt><dd>Awaiting approved fee settings</dd></div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-warm">
    <div class="container-narrow">
        <div class="section-heading">
            <p class="eyebrow">Planned application journey</p>
            <h2>A guided process with clear checkpoints.</h2>
        </div>
        <?php
        $steps = [
            ['Choose a grade', 'Review approved criteria before starting.'],
            ['Create and verify an account', 'Establish a secure identity and confirmed email address.'],
            ['Complete your professional profile', 'Provide biodata, education and employment information.'],
            ['Upload required credentials', 'Submit only the documents requested for the selected grade.'],
            ['Review and declaration', 'Check the application and confirm the declaration.'],
            ['Administrative assessment', 'Receive an approval, query or rejection through the secure portal.'],
        ];
        ?>
        <ol class="journey-list">
            <?php foreach ($steps as $index => [$title, $description]): ?>
                <li>
                    <span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <div><h3><?= $e($title) ?></h3><p><?= $e($description) ?></p></div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<section class="section" id="member-login">
    <div class="container-narrow">
        <?= $view->render('components.empty-state', [
            'label' => 'Secure account access',
            'title' => 'Account registration and sign-in are available.',
            'message' => 'Create and verify a secure account now. The full membership application and member portal remain reserved for their approved stages.',
            'linkPath' => '/register',
            'linkLabel' => 'Create a secure account',
            'e' => $e,
        ]) ?>
        <p class="text-center mt-4">Already registered? <a class="text-link" href="/login">Sign in to your account <span aria-hidden="true">→</span></a></p>
    </div>
</section>
