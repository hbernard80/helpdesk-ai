<?php
declare(strict_types=1);

use App\Services\Utils;

/** @var array<string, mixed> $ticket Ticket à afficher. */
/** @var string $csrfToken Jeton de session transmis aux actions. */
?>
<article class="ticket">

    <h3>
        #<?= (int) $ticket['id'] ?>
        — <?= Utils::escape($ticket['title']) ?>
    </h3>

    <p><?= nl2br(Utils::escape($ticket['description'])) ?></p>

    <p class="meta">
        Statut : <?= Utils::escape($ticket['status']) ?>
        | Catégorie :
        <?= Utils::escape($ticket['category'] ?? 'Non définie') ?>
        | Créé le :
        <?= Utils::escape($ticket['created_at']) ?>
    </p>

    <?php require __DIR__ . '/actions.php'; ?>
</article>
