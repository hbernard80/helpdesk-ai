<?php
declare(strict_types=1);

use App\Services\Utils;

/** @var array{summary: string, steps: list<array{instruction: string, sources: list<string>}>, limitations: string, sources: list<string>} $resolution Proposition vérifiée. */
/** @var string $model Modèle utilisé. */
?>
<section class="panel">
    <p><strong>Proposition IA à vérifier par un technicien avant toute action.</strong></p>
    <p><?= Utils::escape($resolution['summary']) ?></p>
    <ol>
        <?php foreach ($resolution['steps'] as $step): ?>
            <li>
                <p><?= Utils::escape($step['instruction']) ?></p>
                <p class="meta">Références : <?= Utils::escape(implode(', ', $step['sources'])) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
    <?php if ($resolution['limitations'] !== ''): ?>
        <p><strong>Limites :</strong> <?= Utils::escape($resolution['limitations']) ?></p>
    <?php endif; ?>
    <p class="meta">Modèle : <?= Utils::escape($model) ?>. Cette proposition n’est pas enregistrée.</p>
</section>
