<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Csrf;
use App\Services\Database;
use App\Services\Http;
use App\Services\TicketRepository;
use App\Services\TicketValidator;

Http::requirePost();

Csrf::requireValidToken();

$id = TicketValidator::ticketId($_POST['ticket_id'] ?? null);

$category = TicketValidator::category($_POST['category'] ?? null);

if (
    $id === null ||
    $category === null
) {
    Http::abort(400, 'Données de validation invalides.');
}

try {
    $repository = new TicketRepository(
        Database::getConnection()
    );

    $ticket = $repository->findById($id);

    if ($ticket === null) {
        Http::abort(404, 'Ticket introuvable.');
    }

    if (empty($ticket['ai_suggestion'])) {
        Http::abort(400, 'Aucune proposition IA à valider.');
    }

    $repository->validateCategory($id, $category);

    Http::redirect();

} catch (Throwable $exception) {
    error_log($exception->getMessage());
    Http::abort(500, 'Impossible de valider le ticket.');
}
