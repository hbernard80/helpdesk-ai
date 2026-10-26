<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\TicketCategories;
use App\Services\TicketValidator;

/**
 * Vérifie une règle de validation sans dépendance à la base ou à l'IA.
 *
 * @param bool $condition Résultat attendu vrai.
 * @param string $message Règle concernée par un éventuel échec.
 * @return void
 * @throws LogicException Si la règle n'est pas respectée.
 */
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new LogicException($message);
    }
};

$valid = TicketValidator::creation(['title' => '  Réseau indisponible  ', 'description' => "\nConnexion coupée.\n"]);
$check($valid['errors'] === [] && $valid['title'] === 'Réseau indisponible' && $valid['description'] === 'Connexion coupée.', 'Normalisation des textes.');
$check(count(TicketValidator::creation([])['errors']) === 2, 'Champs manquants.');

foreach (['title', 'description'] as $field) {
    foreach ([null, [], ['texte'], 42, true, '', " \r\n\t", "\u{00A0}", "\xFF", "texte\0"] as $invalid) {
        $input = ['title' => 'Titre valide', 'description' => 'Description valide'];
        $input[$field] = $invalid;
        $check(isset(TicketValidator::creation($input)['errors'][$field]), 'Champ invalide accepté : ' . $field);
    }
}

// Les caractères multioctets sont comptés comme caractères, pas comme octets.
$check(TicketValidator::creation(['title' => str_repeat('é', 255), 'description' => str_repeat('é', 30000)])['errors'] === [], 'Longueurs maximales Unicode.');
$check(isset(TicketValidator::creation(['title' => str_repeat('é', 256), 'description' => 'Texte'])['errors']['title']), 'Titre trop long.');
$check(isset(TicketValidator::creation(['title' => 'Titre', 'description' => str_repeat('a', 30001)])['errors']['description']), 'Description trop longue.');
$check(TicketValidator::creation(['title' => 'Titre', 'description' => str_repeat('😀', 16383) . 'abc'])['errors'] === [], 'Limite exacte de stockage TEXT.');
$check(isset(TicketValidator::creation(['title' => 'Titre', 'description' => str_repeat('😀', 16384)])['errors']['description']), 'Dépassement en octets malgré une longueur Unicode autorisée.');

foreach (['1' => 1, '123' => 123, '4294967295' => 4294967295] as $input => $expected) {
    $check(TicketValidator::ticketId((string) $input) === $expected, 'Identifiant valide refusé.');
}
foreach ([null, [], ['1'], 1, true, 1.5, '', '0', '-1', '+1', '01', ' 1', '1 ', "1\n", '1.0', '1e2', '4294967296', str_repeat('9', 100)] as $invalid) {
    $check(TicketValidator::ticketId($invalid) === null, 'Identifiant invalide accepté.');
}
foreach (TicketCategories::ALL as $category) {
    $check(TicketValidator::category($category) === $category, 'Catégorie autorisée refusée.');
}
foreach ([null, [], 1, true, '', 'messagerie', 'Messagerie ', 'Inconnue'] as $invalid) {
    $check(TicketValidator::category($invalid) === null, 'Catégorie invalide acceptée.');
}

echo "OK : types, champs obligatoires, UTF-8, normalisation et limites en caractères et octets.\n";
echo "OK : identifiants INT UNSIGNED et catégories autorisées.\n";
