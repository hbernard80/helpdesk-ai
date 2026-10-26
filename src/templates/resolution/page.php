<?php
declare(strict_types=1);

use App\Services\Utils;

/** @var array<string, mixed> $ticket Ticket concerné. */
/** @var array{summary: string, steps: list<array{instruction: string, sources: list<string>}>, limitations: string, sources: list<string>} $resolution Proposition vérifiée. */
/** @var list<array{source: string, content: string, score: float}> $procedures Procédures retrouvées. */
/** @var string $model Modèle utilisé. */
$pageTitle = 'Proposition de résolution — HelpDesk AI';
$stylesheet = 'assets/styles/app.css';
require __DIR__ . '/../shared/header.php';
?>
<p><a href="/">Retour aux tickets</a></p>
<h1>Proposition de résolution</h1>
<p>Ticket #<?= (int) $ticket['id'] ?> — <?= Utils::escape($ticket['title']) ?></p>

<?php
require __DIR__ . '/proposal.php';
require __DIR__ . '/procedures.php';
require __DIR__ . '/../shared/footer.php';
