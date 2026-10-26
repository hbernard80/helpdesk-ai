<?php
declare(strict_types=1);

/** @var array<string, string> $errors Erreurs de validation ou d'accès aux tickets. */
/** @var string $title Titre saisi. */
/** @var string $description Description saisie. */
/** @var string $csrfToken Jeton de session commun aux formulaires. */
/** @var list<array<string, mixed>> $tickets Tickets à afficher. */
$pageTitle = 'HelpDesk AI - Tickets';
$stylesheet = 'assets/styles/app.min.css';
require __DIR__ . '/../shared/header.php';
?>
<h1>HelpDesk AI</h1>
<p>Gestion des tickets de support informatique</p>

<?php
require __DIR__ . '/form.php';
if ($errors === []) {
    require __DIR__ . '/list.php';
}
require __DIR__ . '/../shared/footer.php';
