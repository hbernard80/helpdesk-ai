<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $tickets Tickets à afficher. */
/** @var string $csrfToken Jeton de session transmis aux actions. */
?>
<section class="panel">
    <h2>Tickets enregistrés (<?= count($tickets) ?>)</h2>

    <?php if ($tickets === []): ?>
        <p>Aucun ticket enregistré.</p>
    <?php endif; ?>

    <?php foreach ($tickets as $ticket): ?>
        <?php require __DIR__ . '/ticket.php'; ?>
    <?php endforeach; ?>

</section>
