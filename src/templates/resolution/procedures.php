<?php
declare(strict_types=1);

use App\Services\Utils;

/** @var list<array{source: string, content: string, score: float}> $procedures Procédures retrouvées. */
/** @var array{sources: list<string>} $resolution Références utilisées par la proposition. */
?>
<section class="panel">
    <h2>Procédures retrouvées</h2>
    <?php foreach ($procedures as $procedure): ?>
        <details>
            <summary>
                <?= Utils::escape($procedure['source']) ?>
                — similarité <?= Utils::escape(number_format($procedure['score'], 4, ',', '')) ?>
                <?= in_array($procedure['source'], $resolution['sources'], true) ? ' — citée' : '' ?>
            </summary>
            <p><?= nl2br(Utils::escape($procedure['content'])) ?></p>
        </details>
    <?php endforeach; ?>
</section>
