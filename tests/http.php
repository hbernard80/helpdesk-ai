<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$temporary = sys_get_temp_dir() . '/helpdesk-http-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
mkdir($temporary . '/sessions', 0700);
$server = null;

/**
 * Vérifie le statut, les en-têtes ou le contenu d'une réponse réelle.
 *
 * @param bool $condition Résultat attendu vrai.
 * @param string $message Règle vérifiée.
 * @return void
 * @throws LogicException Si la réponse est incorrecte.
 */
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new LogicException($message);
    }
};

try {
    $router = '<?php require ' . var_export($root . '/vendor/autoload.php', true) . ';'
        . 'session_save_path(' . var_export($temporary . '/sessions', true) . ');'
        . 'putenv("DB_PASSWORD=");'
        . 'putenv(' . var_export('PROCEDURES_DIRECTORY=' . $temporary . '/missing', true) . ');'
        . <<<'PHP'

use App\Services\Http;
use App\Services\Csrf;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array($path, ['/_http/create-fail', '/_http/create-ok', '/_http/list-fail'], true)) {
    // Injecter une connexion PDO simulée uniquement dans ce serveur de test.
    $pdo = new class($path !== '/_http/create-ok', __DIR__ . '/insert.json') extends PDO {
        public function __construct(private bool $fail, private string $capture)
        {
        }

        /**
         * Prépare une insertion simulée pour tester le traitement réel de création.
         *
         * @param string $query Requête préparée par le repository.
         * @param array<array-key, mixed> $options Options PDO facultatives.
         * @return PDOStatement|false Requête simulée.
         */
        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            return new class($this->fail, $this->capture) extends PDOStatement {
                public function __construct(private bool $fail, private string $capture)
                {
                }

                /**
                 * Capture les valeurs transmises et simule le résultat de l'insertion.
                 *
                 * @param array<array-key, mixed>|null $params Valeurs à insérer.
                 * @return bool Vrai en cas de succès simulé.
                 * @throws PDOException En cas d'échec simulé.
                 */
                public function execute(?array $params = null): bool
                {
                    file_put_contents($this->capture, json_encode($params, JSON_THROW_ON_ERROR));
                    if ($this->fail) {
                        throw new PDOException('technical-insert-failure');
                    }
                    return true;
                }
            };
        }

        /**
         * Fournit l'identifiant du ticket simulé.
         *
         * @param string|null $name Nom de séquence facultatif.
         * @return string|false Identifiant fictif.
         */
        public function lastInsertId(?string $name = null): string|false
        {
            return '42';
        }

        /**
         * Simule une erreur de lecture et détecte les lectures après insertion.
         *
         * @param string $query Requête SQL.
         * @param int|null $fetchMode Mode de lecture.
         * @param mixed ...$fetchModeArgs Arguments additionnels.
         * @return PDOStatement|false Aucun résultat en cas d'erreur simulée.
         * @throws PDOException Toujours, pour simuler l'indisponibilité de la liste.
         */
        public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
        {
            throw new PDOException('technical-list-failure');
        }
    };
    (new ReflectionProperty(App\Services\Database::class, 'connection'))->setValue(null, $pdo);
    require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
    return true;
}

switch (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    case '/_http/redirect': Http::redirect('/');
    case '/_http/json': Http::json(['message' => 'Réponse valide']);
    case '/_http/json-invalid': Http::json(['invalid' => INF]);
    case '/_http/text': Http::text('Texte valide');
    case '/_http/html': Http::html(422); echo '<p>Erreur de saisie</p>'; return true;
    case '/_http/session': Http::json(['token' => Csrf::token()]);
    case '/_http/post': Http::requirePost(); Http::text('Accepté');
}
return false;
PHP;
    file_put_contents($temporary . '/router.php', $router);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($socket === false) {
        throw new RuntimeException('Impossible de réserver un port local : ' . $errorMessage);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = proc_open(
        [PHP_BINARY, '-d', 'xdebug.mode=off', '-d', 'display_errors=0', '-S', $address, '-t', $root . '/src/public', $temporary . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $temporary . '/server.log', 'a'], 2 => ['file', $temporary . '/server.log', 'a']],
        $pipes
    );
    if (!is_resource($server)) {
        throw new RuntimeException('Impossible de démarrer le serveur PHP de test.');
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
    $check($ready, 'Le serveur PHP ne répond pas : ' . ($ready ? '' : file_get_contents($temporary . '/server.log')));

    /**
     * Envoie une requête HTTP sans suivre les redirections.
     *
     * @param string $path Chemin à tester.
     * @param string $method Méthode HTTP.
     * @param array<string, mixed> $form Champs de formulaire.
     * @param string $cookie Cookie de session facultatif.
     * @return array{status: int, headers: array<string, string>, body: string} Réponse reçue.
     * @throws RuntimeException Si la connexion échoue.
     */
    $request = static function (string $path, string $method = 'GET', array $form = [], string $cookie = '') use ($address): array {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n" . ($cookie === '' ? '' : 'Cookie: ' . $cookie . "\r\n"),
            'content' => http_build_query($form),
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 5,
        ]]);
        $stream = fopen('http://' . $address . $path, 'r', false, $context);
        if ($stream === false) {
            throw new RuntimeException('Requête impossible : ' . $path);
        }
        $body = stream_get_contents($stream);
        $metadata = stream_get_meta_data($stream);
        fclose($stream);
        $lines = $metadata['wrapper_data'];
        preg_match('/^HTTP\/\S+ (\d+)/', $lines[0], $status);
        $headers = [];
        foreach (array_slice($lines, 1) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower($name)] = trim($value);
            }
        }
        return ['status' => (int) $status[1], 'headers' => $headers, 'body' => $body];
    };

    $response = $request('/_http/redirect', 'POST');
    $check($response['status'] === 303 && $response['headers']['location'] === '/', 'Redirection 303 avec Location attendue.');
    $response = $request('/_http/json');
    $check($response['status'] === 200 && $response['headers']['content-type'] === 'application/json; charset=utf-8', 'En-têtes JSON incorrects.');
    $check(json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)['message'] === 'Réponse valide', 'Réponse JSON incorrecte.');
    $response = $request('/_http/json-invalid');
    $check($response['status'] === 500 && isset(json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)['error']), 'Échec de sérialisation non géré.');
    $response = $request('/_http/text');
    $check($response['status'] === 200 && $response['headers']['content-type'] === 'text/plain; charset=utf-8' && $response['body'] === 'Texte valide', 'Réponse texte incorrecte.');
    $response = $request('/_http/html');
    $check($response['status'] === 422 && $response['headers']['content-type'] === 'text/html; charset=utf-8', 'Réponse HTML 422 incorrecte.');
    foreach (['/_http/text', '/_http/json'] as $path) {
        $check($request($path, 'HEAD')['body'] === '', 'HEAD ne doit pas renvoyer de corps.');
    }
    $check($request('/_http/post', 'POST')['status'] === 200, 'Une méthode autorisée doit passer.');

    foreach (['analyze-ticket.php', 'validate-ticket.php', 'resolve-ticket.php'] as $endpoint) {
        $response = $request('/' . $endpoint);
        $check($response['status'] === 405 && $response['headers']['allow'] === 'POST' && $response['headers']['content-type'] === 'text/plain; charset=utf-8', 'Réponse 405 incorrecte : ' . $endpoint);
        $response = $request('/' . $endpoint, 'POST');
        $check($response['status'] === 403 && $response['headers']['content-type'] === 'text/plain; charset=utf-8', 'Réponse CSRF incorrecte : ' . $endpoint);
    }
    foreach (['test-classifier.php', 'test-embedding.php', 'test-procedures.php', 'test-search.php'] as $endpoint) {
        $response = $request('/' . $endpoint, 'POST');
        $check($response['status'] === 405 && $response['headers']['allow'] === 'GET, HEAD' && $response['headers']['content-type'] === 'application/json; charset=utf-8', 'Refus JSON incorrect : ' . $endpoint);
        $check(isset(json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)['error']), 'Objet error manquant.');
    }
    foreach (['test-database.php', 'test-ollama.php'] as $endpoint) {
        $response = $request('/' . $endpoint, 'POST');
        $check($response['status'] === 405 && $response['headers']['allow'] === 'GET, HEAD', 'Méthode de diagnostic non contrôlée.');
    }
    foreach (['/index.php', '/test-database.php', '/test-procedures.php'] as $path) {
        $response = $request($path);
        $check($response['status'] === 500 && !str_contains($response['body'], 'DB_PASSWORD') && !str_contains($response['body'], $temporary), 'Une erreur technique doit rester dans les journaux.');
    }
    $session = $request('/_http/session');
    $token = json_decode($session['body'], true, 512, JSON_THROW_ON_ERROR)['token'];
    $cookie = explode(';', $session['headers']['set-cookie'])[0];
    $response = $request('/index.php', 'POST', ['csrf_token' => $token, 'title' => 'Titre', 'description' => ''], $cookie);
    $check($response['status'] === 422 && $response['headers']['content-type'] === 'text/html; charset=utf-8' && str_contains($response['body'], 'value="Titre"'), 'Le formulaire invalide doit conserver sa réponse HTML et ses valeurs.');
    $form = ['csrf_token' => $token, 'title' => '<Titre conservé>', 'description' => 'Description <script>alert(1)</script>'];
    foreach (['/index.php', '/_http/create-fail'] as $path) {
        $response = $request($path, 'POST', $form, $cookie);
        $check($response['status'] === 500 && $response['headers']['content-type'] === 'text/html; charset=utf-8', 'Un échec de connexion ou insertion doit renvoyer un formulaire HTML en 500.');
        $check(!isset($response['headers']['location']) && str_contains($response['body'], 'Votre saisie est conservée.'), 'Un échec ne doit pas rediriger ni perdre la saisie.');
        $check(str_contains($response['body'], 'value="&lt;Titre conservé&gt;"') && str_contains($response['body'], 'Description &lt;script&gt;alert(1)&lt;/script&gt;'), 'Les deux champs doivent être conservés et échappés.');
        $check(str_contains($response['body'], 'value="' . $token . '"') && !str_contains($response['body'], 'technical-insert-failure') && !str_contains($response['body'], 'DB_PASSWORD'), 'Le jeton reste disponible et les détails techniques restent privés.');
        $check(!str_contains($response['body'], 'Aucun ticket enregistré.'), 'Un échec ne doit pas afficher une liste vide trompeuse.');
    }
    $response = $request('/_http/create-ok', 'POST', $form, $cookie);
    $check($response['status'] === 303 && $response['headers']['location'] === '/', 'La création réussie doit toujours rediriger, même si la lecture de liste échouerait.');
    $inserted = json_decode(file_get_contents($temporary . '/insert.json'), true, 512, JSON_THROW_ON_ERROR);
    $check($inserted === ['title' => $form['title'], 'description' => $form['description']], 'Le repository doit recevoir les champs validés.');
    $response = $request('/_http/list-fail');
    $check($response['status'] === 500 && str_contains($response['body'], 'Impossible de charger les tickets') && !str_contains($response['body'], 'technical-list-failure'), 'L’erreur de lecture doit être distincte de l’échec de création.');
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    foreach (glob($temporary . '/sessions/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($temporary . '/sessions');
    foreach (glob($temporary . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($temporary);
}

echo "OK : statuts HTTP, Content-Type, Allow, Location, JSON, HEAD et erreurs publiques.\n";
echo "OK : échecs de connexion et insertion, saisie conservée, erreur de lecture et redirection après création.\n";
