<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\EmbeddingClient;
use App\Services\Http;
use App\Services\ProcedureSearch;

Http::requireMethods(['GET', 'HEAD'], json: true);

try {
    $search = new ProcedureSearch(new EmbeddingClient());

    $results = $search->search(
        "Je ne peux plus envoyer de mails depuis Outlook.",
        3
    );

    Http::json(
        array_map(
            fn(array $result) => [
                'source' => $result['source'],
                'score' => round($result['score'], 4)
            ],
            $results
        )
    );

} catch (Throwable $exception) {
    error_log($exception->getMessage());
    Http::abort(500, 'Erreur de recherche sémantique. Consultez les journaux du serveur.', json: true);
}
