<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\EmbeddingClient;
use App\Services\LlmClient;
use App\Services\ProcedureIndexer;
use App\Services\ProcedureRepository;
use App\Services\ProcedureSearch;

$temporary = sys_get_temp_dir() . '/helpdesk-ollama-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
$server = null;
$environment = [];

/**
 * Vérifie les requêtes réellement reçues par le faux serveur Ollama.
 *
 * @param bool $condition Résultat attendu vrai.
 * @param string $message Contrat contrôlé.
 * @return void
 * @throws LogicException Si le contrat n'est pas respecté.
 */
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new LogicException($message);
    }
};

/**
 * Attend une erreur contrôlée du transport ou d'un client spécialisé.
 *
 * @param callable(): mixed $action Appel à vérifier.
 * @param string $message Fragment attendu dans l'erreur.
 * @return RuntimeException|InvalidArgumentException Exception contrôlée reçue.
 * @throws LogicException Si l'appel réussit ou si le message est inattendu.
 */
$mustReject = static function (callable $action, string $message): RuntimeException|InvalidArgumentException {
    try {
        $action();
    } catch (RuntimeException | InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), $message)) {
            throw new LogicException('Erreur inattendue : ' . $exception->getMessage());
        }
        return $exception;
    }
    throw new LogicException('Appel incorrectement accepté : ' . $message);
};

try {
    $router = <<<'PHP'
<?php
$directory = __DIR__;
file_put_contents($directory . '/request.json', json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $_SERVER['REQUEST_URI'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
    'accept' => $_SERVER['HTTP_ACCEPT'] ?? '',
    'payload' => json_decode(file_get_contents('php://input'), true),
], JSON_THROW_ON_ERROR));
$scenario = json_decode(file_get_contents($directory . '/response.json'), true, 512, JSON_THROW_ON_ERROR);
if (isset($scenario['sequence'])) {
    $scenario['body'] = array_shift($scenario['sequence']);
    file_put_contents($directory . '/response.json', json_encode($scenario, JSON_THROW_ON_ERROR));
}
usleep($scenario['delay'] * 1000);
http_response_code($scenario['status']);
header('Content-Type: application/json');
if ($scenario['status'] === 302) {
    header('Location: /unexpected-redirect');
}
echo $scenario['body'];
PHP;
    file_put_contents($temporary . '/router.php', $router);

    /**
     * Configure la réponse du faux serveur pour le scénario suivant.
     *
     * @param string $body Corps brut, éventuellement mal formé.
     * @param int $status Statut HTTP à renvoyer.
     * @param int $delay Délai artificiel en millisecondes.
     * @return void
     * @throws JsonException Si la configuration du scénario n'est pas encodable.
     */
    $respond = static function (string $body, int $status = 200, int $delay = 0) use ($temporary): void {
        file_put_contents($temporary . '/response.json', json_encode(compact('body', 'status', 'delay'), JSON_THROW_ON_ERROR));
    };
    $respond('{}');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($socket === false) {
        throw new RuntimeException('Impossible de réserver un port local : ' . $errorMessage);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = proc_open(
        [PHP_BINARY, '-d', 'xdebug.mode=off', '-d', 'display_errors=0', '-S', $address, $temporary . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $temporary . '/server.log', 'a'], 2 => ['file', $temporary . '/server.log', 'a']],
        $pipes
    );
    if (!is_resource($server)) {
        throw new RuntimeException('Impossible de démarrer le faux serveur Ollama.');
    }
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.1);
        if ($probe !== false) {
            fclose($probe);
            $ready = true;
            break;
        }
        usleep(50000);
    }
    $check($ready, 'Le faux serveur Ollama ne répond pas.');
    $url = 'http://' . $address;
    foreach (['OLLAMA_URL' => $url, 'OLLAMA_MODEL' => 'configured-chat', 'OLLAMA_EMBEDDING_MODEL' => 'configured-embed'] as $name => $value) {
        $environment[$name] = getenv($name);
        putenv($name . '=' . $value);
    }

    /**
     * Lit la dernière requête HTTP reçue par le faux serveur.
     *
     * @return array<string, mixed> Méthode, chemin, en-têtes et corps JSON reçus.
     * @throws JsonException Si la capture de requête est invalide.
     */
    $received = static function () use ($temporary): array {
        return json_decode(file_get_contents($temporary . '/request.json'), true, 512, JSON_THROW_ON_ERROR);
    };

    $chat = new LlmClient($url . '/', 'custom-chat');
    $embedding = new EmbeddingClient($url . '/', 'custom-embed');
    $messages = [['role' => 'user', 'content' => 'Réseau coupé']];
    $schema = ['type' => 'object'];
    $respond('{"message":{"content":"Réponse"},"done":true}');
    $result = $chat->chat($messages, 500, $schema);
    $request = $received();
    $check($result['message']['content'] === 'Réponse' && $result['done'] === true, 'Réponse chat et métadonnées conservées.');
    $check($request['method'] === 'POST' && $request['path'] === '/api/chat', 'Méthode et URL du chat.');
    $check($request['content_type'] === 'application/json' && $request['accept'] === 'application/json', 'En-têtes JSON communs.');
    $check($request['payload']['model'] === 'custom-chat' && $request['payload']['messages'] === $messages && $request['payload']['format'] === $schema, 'Modèle, messages et schéma transmis.');
    $check($request['payload']['stream'] === false && $request['payload']['think'] === false && $request['payload']['options'] === ['num_predict' => 500, 'temperature' => 0], 'Options de génération conservées.');

    $respond('{"message":{"content":"Texte généré"}}');
    $check($chat->generate('Consigne')['response'] === 'Texte généré', 'Réponse generate.');
    $request = $received();
    $check($request['path'] === '/api/chat' && $request['payload']['messages'] === [['role' => 'user', 'content' => 'Consigne']], 'Requête generate.');
    $respond('{"embeddings":[[0.1,-0.2,1]]}');
    $check($embedding->embed('Texte à vectoriser') === [0.1, -0.2, 1], 'Vecteur numérique valide.');
    $request = $received();
    $check($request['path'] === '/api/embed' && $request['payload'] === ['model' => 'custom-embed', 'input' => 'Texte à vectoriser'], 'Requête embed.');
    $check($request['content_type'] === 'application/json' && $request['accept'] === 'application/json', 'En-têtes de vectorisation.');
    (new EmbeddingClient())->embed('Texte');
    $check($received()['payload']['model'] === 'configured-embed', 'Configuration commune du modèle d’embedding.');
    $respond('{"message":{"content":"OK"}}');
    (new LlmClient())->chat($messages);
    $check($received()['payload']['model'] === 'configured-chat', 'Configuration commune du modèle de génération.');

    foreach ([
        [500, 'indisponible', 'HTTP Ollama (500)'],
        [404, '{"error":"model missing"}', 'HTTP Ollama (404)'],
        [302, '{}', 'HTTP Ollama (302)'],
        [200, 'JSON invalide', 'JSON Ollama invalide'],
        [200, 'null', 'objet JSON attendu'],
        [200, '[]', 'objet JSON attendu'],
        [200, '{"error":"model missing"}', 'model missing'],
        [200, '{"error":{"detail":"failure"}}', 'erreur non détaillée'],
    ] as [$status, $body, $message]) {
        $respond($body, $status);
        $mustReject(fn() => $chat->chat($messages), $message);
        $mustReject(fn() => $embedding->embed('Texte'), $message);
    }
    foreach ([
        '{}',
        '{"embeddings":null}',
        '{"embeddings":"invalid"}',
        '{"embeddings":[]}',
        '{"embeddings":{"unexpected":[1]}}',
        '{"embeddings":[[1],[2]]}',
        '{"embeddings":[[]]}',
        '{"embeddings":[["0.1"]]}',
        '{"embeddings":[[true]]}',
        '{"embeddings":[[null]]}',
        '{"embeddings":[[[1]]]}',
        '{"embeddings":[{"x":1}]}',
        '{"embeddings":[[1e999]]}',
        '{"embeddings":[[-1e999]]}',
        '{"embeddings":[[0,-0.0,0]]}',
    ] as $body) {
        $respond($body);
        $mustReject(fn() => $embedding->embed('Texte'), '');
    }

    $indexPath = $temporary . '/index.json';
    file_put_contents($temporary . '/PROC-001.md', 'Première procédure.');
    file_put_contents($temporary . '/PROC-002.md', 'Seconde procédure.');
    $indexer = new ProcedureIndexer(new ProcedureRepository($temporary), $embedding, $indexPath);
    file_put_contents($indexPath, 'index-original');
    file_put_contents($temporary . '/response.json', json_encode([
        'status' => 200, 'delay' => 0, 'body' => '',
        'sequence' => ['{"embeddings":[[1,0,0]]}', '{"embeddings":[[1,0]]}'],
    ], JSON_THROW_ON_ERROR));
    $mustReject(fn() => $indexer->build(), 'Dimensions de vecteurs incompatibles');
    $check(file_get_contents($indexPath) === 'index-original', 'Une indexation incompatible doit préserver l’index existant.');
    $respond('{"embeddings":[[1,0,0]]}');
    $check($indexer->build() === 2, 'Indexation de vecteurs compatibles.');

    $search = new ProcedureSearch($embedding, $indexPath);
    foreach ([
        [], ['bad'], [true], [null], [0, 0], ['x' => 1], [[1]],
    ] as $invalidVector) {
        file_put_contents($indexPath, json_encode([['source' => 'PROC.md', 'content' => 'Texte', 'embedding' => $invalidVector]], JSON_THROW_ON_ERROR));
        $mustReject(fn() => $search->search('Question'), '');
    }
    foreach (['{}', '[null]', '[{"source":42,"content":"Texte"}]', '[{"source":"PROC.md","content":"Texte"}]'] as $invalidIndex) {
        file_put_contents($indexPath, $invalidIndex);
        $mustReject(fn() => $search->search('Question'), '');
    }
    file_put_contents($indexPath, '[{"source":"PROC.md","content":"Texte","embedding":[1,0]}]');
    $respond('{"embeddings":[[1,0,0]]}');
    $mustReject(fn() => $search->search('Question'), 'Dimensions de vecteurs incompatibles');
    file_put_contents($indexPath, '[{"source":"A.md","content":"A","embedding":[1,0]},{"source":"B.md","content":"B","embedding":[1,0,0]}]');
    $mustReject(fn() => $search->search('Question'), 'Dimensions de vecteurs incompatibles');

    foreach ([1.0, 1e300, 1e-300] as $scale) {
        file_put_contents($indexPath, json_encode([
            ['source' => 'opposé.md', 'content' => 'Opposé', 'embedding' => [-$scale, -$scale]],
            ['source' => 'proche.md', 'content' => 'Proche', 'embedding' => [$scale, $scale]],
            ['source' => 'orthogonal.md', 'content' => 'Orthogonal', 'embedding' => [$scale, -$scale]],
        ], JSON_THROW_ON_ERROR));
        $respond(json_encode(['embeddings' => [[$scale, $scale]]], JSON_THROW_ON_ERROR));
        $results = $search->search('Question');
        $check(array_column($results, 'source') === ['proche.md', 'orthogonal.md', 'opposé.md'], 'Classement cosinus incorrect.');
        foreach ($results as $result) {
            $check(is_finite($result['score']) && abs($result['score']) <= 1, 'Score non fini ou hors limites.');
        }
        $check(abs($results[0]['score'] - 1.0) < 1e-12 && abs($results[2]['score'] + 1.0) < 1e-12, 'Similarité incorrecte pour les valeurs extrêmes.');
    }
    $exception = $mustReject(fn() => $embedding->embed("\xFF"), 'sérialisation JSON');
    $check($exception->getPrevious() instanceof JsonException, 'Cause de sérialisation conservée.');
    $mustReject(fn() => $chat->generate("\xFF"), 'sérialisation JSON');
    $slowChat = new LlmClient($url, 'custom-chat', 1);
    $slowEmbedding = new EmbeddingClient($url, 'custom-embed', 1);
    $respond('{}', 200, 2000);
    $mustReject(fn() => $slowChat->chat($messages), 'communication avec Ollama');
    $mustReject(fn() => $slowEmbedding->embed('Texte'), 'communication avec Ollama');
    proc_terminate($server);
    proc_close($server);
    $server = null;
    $mustReject(fn() => $slowChat->chat($messages), 'communication avec Ollama');
    $mustReject(fn() => $slowEmbedding->embed('Texte'), 'communication avec Ollama');
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    foreach ($environment as $name => $value) {
        putenv($value === false ? $name : $name . '=' . $value);
    }
    foreach (glob($temporary . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($temporary);
}

echo "OK : requêtes chat, generate et embed, modèles, options et en-têtes communs.\n";
echo "OK : erreurs HTTP, JSON, API, réseau et délai ; vecteurs numériques validés.\n";
echo "OK : structure des embeddings, vecteurs non nuls, dimensions et index contrôlés, scores stables.\n";
