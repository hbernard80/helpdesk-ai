<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Config;
use App\Services\EmbeddingClient;
use App\Services\Http;

Http::requireMethods(['GET', 'HEAD'], json: true);

try {
    $client = new EmbeddingClient();

    $vector = $client->embed(
        'Impossible de se connecter à Outlook.'
    );

    Http::json([
        'model' => Config::get('ollama.embedding_model'),
        'dimensions' => count($vector),
        'preview' => array_slice($vector, 0, 5)
    ]);

} catch (Throwable $exception) {
    error_log($exception->getMessage());

    Http::abort(500, 'Impossible de générer l’embedding. Consultez les journaux du serveur.', json: true);
}
