<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Config;
use App\Services\Csrf;
use App\Services\Database;
use App\Services\Http;
use App\Services\LlmClient;
use App\Services\TicketClassifier;
use App\Services\TicketRepository;
use App\Services\TicketValidator;

Http::requirePost();

Csrf::requireValidToken();

$id = TicketValidator::ticketId($_POST['ticket_id'] ?? null);

if ($id === null) {
    Http::abort(400, 'Identifiant de ticket invalide.');
}

try {
    $repository = new TicketRepository(Database::getConnection());

    $ticket = $repository->findById($id);

    if ($ticket === null) {
        Http::abort(404, 'Ticket introuvable.');
    }

    $model = Config::get('ollama.model');
    $llm = new LlmClient(model: $model);

    $classifier = new TicketClassifier($llm);

    $result = $classifier->classify($ticket['title'], $ticket['description']);

    $repository->saveAiSuggestion($id, $result['category'], $model);

    Http::redirect();
} catch (Throwable $exception) {
    error_log('Analyse du ticket ' . $id . ' : ' . $exception->getMessage());
    Http::abort(500, 'Impossible d\'analyser le ticket. ' . 'Consultez les journaux du serveur.');
}
