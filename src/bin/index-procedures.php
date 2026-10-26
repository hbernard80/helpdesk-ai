<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Config;
use App\Services\EmbeddingClient;
use App\Services\ProcedureIndexer;
use App\Services\ProcedureRepository;

$indexer = new ProcedureIndexer(
    new ProcedureRepository(Config::get('procedures.directory')),
    new EmbeddingClient()
);

$count = $indexer->build();

echo "$count procédures indexées.\n";
