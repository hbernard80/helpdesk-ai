<?php
declare(strict_types=1);

use App\Services\Utils;

/** @var string $pageTitle Titre de la page. */
/** @var string $stylesheet Chemin de la feuille de style. */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Utils::escape($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= Utils::escape($stylesheet) ?>">
</head>
<body>
