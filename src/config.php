<?php
declare(strict_types=1);

/**
 * Lit une variable d'environnement ou utilise la valeur par défaut.
 *
 * @param string $name Nom de la variable.
 * @param string $default Valeur utilisée si la variable est absente ou vide.
 * @return string Valeur de configuration.
 */
$env = static function (string $name, string $default): string {
    $value = getenv($name);

    return $value === false || $value === '' ? $default : $value;
};

$root = dirname(__DIR__);

return [
    'db.host' => $env('DB_HOST', 'db'),
    'db.name' => $env('DB_NAME', 'helpdesk'),
    'db.user' => $env('DB_USER', 'helpdesk'),
    'db.password' => $env('DB_PASSWORD', ''),
    'ollama.url' => $env('OLLAMA_URL', 'http://ollama:11434'),
    'ollama.model' => $env('OLLAMA_MODEL', 'qwen3:4b'),
    'ollama.embedding_model' => $env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    'qdrant.url' => $env('QDRANT_URL', 'http://qdrant:6333'),
    'qdrant.collection' => $env('QDRANT_COLLECTION', 'helpdesk_procedures'),
    'procedures.directory' => $env('PROCEDURES_DIRECTORY', $root . '/documents/fictifs'),
    'procedures.index_path' => $env('PROCEDURES_INDEX_PATH', $root . '/storage/procedures-index.json'),
];
