<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Config;
use App\Services\Csrf;
use App\Services\Database;
use App\Services\EmbeddingClient;
use App\Services\Http;
use App\Services\LlmClient;
use App\Services\ProcedureSearch;
use App\Services\ResolutionGenerator;
use App\Services\TicketRepository;
use App\Services\TicketValidator;

Http::requirePost();

Csrf::requireValidToken();

$id = TicketValidator::ticketId($_POST['ticket_id'] ?? null);
if ($id === null) {
    Http::abort(400, 'Identifiant de ticket invalide.');
}

try {
    $ticket = (new TicketRepository(Database::getConnection()))->findById($id);
    if ($ticket === null) {
        Http::abort(404, 'Ticket introuvable.');
    }
    $model = Config::get('ollama.model');
    $search = new ProcedureSearch(new EmbeddingClient());
    $procedures = $search->search($ticket['title'] . "\n" . $ticket['description'], 3);
    $resolution = (new ResolutionGenerator(new LlmClient(model: $model)))->generate(
        $ticket['title'], $ticket['description'], $procedures
    );
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    Http::abort(500, 'Impossible de générer la proposition. Consultez les journaux du serveur.');
}

Http::html();
require __DIR__ . '/../templates/resolution/page.php';
