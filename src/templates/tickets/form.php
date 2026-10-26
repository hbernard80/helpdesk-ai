<?php
declare(strict_types=1);

use App\Services\TicketValidator;
use App\Services\Utils;

/** @var array<string, string> $errors Erreurs de validation ou d'enregistrement. */
/** @var string $title Titre saisi. */
/** @var string $description Description saisie. */
/** @var string $csrfToken Jeton de session. */
?>
<section class="panel">
    <h2>Nouveau ticket</h2>

    <?php foreach ($errors as $error): ?>
        <p class="error" role="alert"><?= Utils::escape($error) ?></p>
    <?php endforeach; ?>

    <form method="post" action="/">
        <input type="hidden" name="csrf_token" value="<?= Utils::escape($csrfToken) ?>">
        <label for="title">Titre</label>
        <input
            type="text"
            id="title"
            name="title"
            maxlength="<?= TicketValidator::TITLE_MAX_LENGTH ?>"
            required
            value="<?= Utils::escape($title) ?>"
        >

        <label for="description">Description</label>
        <textarea
            id="description"
            name="description"
            maxlength="<?= TicketValidator::DESCRIPTION_MAX_LENGTH ?>"
            required
        ><?= Utils::escape($description) ?></textarea>

        <button type="submit">Créer le ticket</button>
    </form>
</section>
