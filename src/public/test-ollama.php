<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Http;
use App\Services\LlmClient;

Http::requireMethods(['GET', 'HEAD']);

try {
    $client = new LlmClient();

    $response = $client->chat([
        [
            'role' => 'system',
            'content' => 'Tu es un assistant informatique. Réponds en français, en deux phrases maximum.',
        ],
        [
            'role' => 'user',
            'content' => 'Qu’est-ce qu’une adresse IP ?',
        ],
    ]);

    Http::text($response['message']['content'] ?? 'Aucune réponse générée.');
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    Http::abort(500, 'Impossible de contacter Ollama. Consultez les journaux du serveur.');
}
