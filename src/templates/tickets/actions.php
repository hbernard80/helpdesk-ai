<?php
declare(strict_types=1);

use App\Services\TicketCategories;
use App\Services\Utils;

/** @var array<string, mixed> $ticket Ticket concerné par les actions. */
/** @var string $csrfToken Jeton de session commun aux formulaires. */
?>
<!-- Analyse IA -->
<form method="post" action="/analyze-ticket.php">
    <input type="hidden" name="csrf_token" value="<?= Utils::escape($csrfToken) ?>">
    <input
        type="hidden"
        name="ticket_id"
        value="<?= (int) $ticket['id'] ?>"
    >

    <button type="submit">
        Analyser avec l'IA
    </button>
</form>

<!-- Proposition IA et validation humaine -->
<form method="post" action="/resolve-ticket.php">
    <input type="hidden" name="csrf_token" value="<?= Utils::escape($csrfToken) ?>">
    <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
    <button type="submit">Proposer une résolution avec les procédures</button>
</form>

<?php if (!empty($ticket['ai_suggestion'])): ?>

    <p>
        <strong>Proposition IA :</strong>
        <?= Utils::escape($ticket['ai_suggestion']) ?>
    </p>

    <?php if (!(bool) $ticket['validated_by_human']): ?>

        <form method="post" action="/validate-ticket.php">
            <input type="hidden" name="csrf_token" value="<?= Utils::escape($csrfToken) ?>">
            <input
                type="hidden"
                name="ticket_id"
                value="<?= (int) $ticket['id'] ?>"
            >

            <label>
                Catégorie retenue :

                <select name="category">
                    <?php foreach (TicketCategories::ALL as $category): ?>
                        <option
                            value="<?= Utils::escape($category) ?>"
                            <?= $category === $ticket['ai_suggestion']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= Utils::escape($category) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button type="submit">
                Valider la catégorie
            </button>
        </form>

    <?php else: ?>

        <p>
            Catégorie validée par un technicien.
        </p>

    <?php endif; ?>

<?php endif; ?>
