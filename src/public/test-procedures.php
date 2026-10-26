<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Config;
use App\Services\Http;
use App\Services\ProcedureRepository;

Http::requireMethods(['GET', 'HEAD'], json: true);

try {
    $repository = new ProcedureRepository(Config::get('procedures.directory'));

    $procedures = $repository->findAll();

    Http::json([
        'count' => count($procedures),
        'documents' => array_column($procedures, 'source'),
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    Http::abort(500, 'Impossible de charger les procédures. Consultez les journaux du serveur.', json: true);
}
