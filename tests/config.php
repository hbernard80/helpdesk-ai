<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\Config;

$variables = [
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
    'OLLAMA_URL', 'OLLAMA_MODEL', 'OLLAMA_EMBEDDING_MODEL',
    'QDRANT_URL', 'QDRANT_COLLECTION',
    'PROCEDURES_DIRECTORY', 'PROCEDURES_INDEX_PATH',
];
$original = [];

/**
 * Vérifie une propriété attendue de la configuration.
 *
 * @param bool $condition Résultat attendu vrai.
 * @param string $message Message d'échec sans valeur sensible.
 * @return void
 * @throws LogicException Si la propriété n'est pas respectée.
 */
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new LogicException($message);
    }
};

try {
    foreach ($variables as $variable) {
        $original[$variable] = getenv($variable);
        putenv($variable);
    }

    $defaults = require __DIR__ . '/../src/config.php';
    $check($defaults['db.host'] === 'db' && $defaults['db.password'] === '', 'Valeurs MySQL par défaut incorrectes.');
    $check($defaults['ollama.url'] === 'http://ollama:11434', 'URL Ollama par défaut incorrecte.');
    $check($defaults['ollama.model'] === 'qwen3:4b', 'Modèle de génération par défaut incorrect.');
    $check($defaults['ollama.embedding_model'] === 'nomic-embed-text', 'Modèle d’embedding par défaut incorrect.');
    $check($defaults['qdrant.url'] === 'http://qdrant:6333', 'URL Qdrant par défaut incorrecte.');
    $check($defaults['qdrant.collection'] === 'helpdesk_procedures', 'Alias Qdrant par défaut incorrect.');
    $root = dirname(__DIR__);
    $check($defaults['procedures.directory'] === $root . '/documents/fictifs', 'Chemin local du corpus incorrect.');
    $check($defaults['procedures.index_path'] === $root . '/storage/procedures-index.json', 'Chemin local de l’index incorrect.');

    $overrides = [
        'DB_HOST' => ['db.host', 'database-test'],
        'DB_NAME' => ['db.name', 'helpdesk-test'],
        'DB_USER' => ['db.user', 'test-user'],
        'DB_PASSWORD' => ['db.password', '0'],
        'OLLAMA_URL' => ['ollama.url', 'http://localhost:12345'],
        'OLLAMA_MODEL' => ['ollama.model', 'generation-test'],
        'OLLAMA_EMBEDDING_MODEL' => ['ollama.embedding_model', 'embedding-test'],
        'QDRANT_URL' => ['qdrant.url', 'http://localhost:6333'],
        'QDRANT_COLLECTION' => ['qdrant.collection', 'test_procedures'],
        'PROCEDURES_DIRECTORY' => ['procedures.directory', $root . '/documents/test'],
        'PROCEDURES_INDEX_PATH' => ['procedures.index_path', $root . '/storage/test-index.json'],
    ];
    foreach ($overrides as $variable => [$key, $value]) {
        putenv($variable . '=' . $value);
    }
    foreach ($overrides as [$key, $value]) {
        $check(Config::get($key) === $value, 'Surcharge non prise en compte : ' . $key);
    }

    putenv('OLLAMA_MODEL=');
    $empty = require __DIR__ . '/../src/config.php';
    $check($empty['ollama.model'] === $defaults['ollama.model'], 'Une variable vide doit utiliser la valeur par défaut.');
    $check(Config::get('ollama.model') === 'generation-test', 'La configuration doit être chargée une seule fois.');

    try {
        Config::get('unknown.key');
        throw new LogicException('Une clé inconnue doit être refusée.');
    } catch (InvalidArgumentException) {
        // Une faute de frappe doit être signalée explicitement.
    }
} finally {
    foreach ($original as $variable => $value) {
        putenv($value === false ? $variable : $variable . '=' . $value);
    }
}

echo "OK : valeurs par défaut, chemins locaux et surcharges d'environnement.\n";
echo "OK : variables vides, valeur zéro, chargement unique et rejet des clés inconnues.\n";
