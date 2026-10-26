<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Http;
use App\Services\LlmClient;
use App\Services\TicketClassifier;

Http::requireMethods(['GET', 'HEAD'], json: true);

try {
    $llm = new LlmClient();

    $classifier = new TicketClassifier($llm);

    $result = $classifier->classify(
        'Problème Outlook',
        "Depuis ce matin, je ne peux plus envoyer ni recevoir de mails."
    );

    Http::json($result);

} catch (Throwable $exception) {
    error_log($exception->getMessage());
    Http::abort(500, 'Impossible de classer le ticket. Consultez les journaux du serveur.', json: true);
}
