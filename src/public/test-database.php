<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Database;
use App\Services\Http;

Http::requireMethods(['GET', 'HEAD']);

try {
    $pdo = Database::getConnection();

    $statement = $pdo->query(
        'SELECT id, title, status, created_at
         FROM tickets
         ORDER BY id DESC'
    );

    $tickets = $statement->fetchAll();

    $body = "Connexion MySQL réussie !\n\n";
    $body .= 'Nombre de tickets : ' . count($tickets) . "\n\n";

    foreach ($tickets as $ticket) {
        $body .= sprintf(
            "#%d - %s - %s\n",
            $ticket['id'],
            $ticket['title'],
            $ticket['status']
        );
    }
    Http::text($body);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    Http::abort(500, 'Impossible de contacter MySQL. Consultez les journaux du serveur.');
}
