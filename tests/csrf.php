<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\Csrf;

$sessionDirectory = sys_get_temp_dir() . '/helpdesk-csrf-' . bin2hex(random_bytes(8));
mkdir($sessionDirectory, 0700);
session_save_path($sessionDirectory);

/**
 * Vérifie une condition du scénario de protection CSRF.
 *
 * @param bool $condition Résultat attendu vrai.
 * @param string $label Message à signaler en cas d'échec.
 * @return void
 * @throws LogicException Si la condition est fausse.
 */
$check = static function (bool $condition, string $label): void {
    if (!$condition) {
        throw new LogicException($label);
    }
};

/**
 * Exécute un traitement réel dans un processus isolé et récupère son statut HTTP.
 *
 * @param string $endpoint Nom du script situé dans src/public.
 * @param string $method Méthode HTTP simulée.
 * @param array<string, mixed> $post Données de formulaire à transmettre.
 * @param string $sessionId Identifiant de la session à utiliser.
 * @param string|null $body Sortie du traitement, récupérée pour vérifier le formulaire rendu.
 * @return int Statut HTTP enregistré avant la fin du processus.
 * @throws RuntimeException Si le processus échoue ou ne renvoie aucun statut.
 */
$request = static function (string $endpoint, string $method, array $post, string $sessionId, ?string &$body = null) use ($sessionDirectory): int {
    $code = 'session_save_path(' . var_export($sessionDirectory, true) . ');'
        . 'session_id(' . var_export($sessionId, true) . ');'
        . '$_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ';'
        . '$_POST = ' . var_export($post, true) . ';'
        . 'putenv("DB_PASSWORD=");'
        . 'set_error_handler(function ($severity, $message) { throw new ErrorException($message, 0, $severity); });'
        . 'register_shutdown_function(function () { echo "\\nSTATUS:" . http_response_code(); });'
        . 'require ' . var_export(__DIR__ . '/../src/public/' . $endpoint, true) . ';';
    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Impossible de lancer le traitement PHP.');
    }
    $output = stream_get_contents($pipes[1]);
    $body = $output;
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 || !preg_match('/STATUS:(\d+)$/', $output, $matches)) {
        throw new RuntimeException($endpoint . ': ' . $errors . $output);
    }
    return (int) $matches[1];
};

try {
    session_id('csrf-test-first');
    $token = Csrf::token();
    $firstSessionId = session_id();
    $check(strlen($token) === 64 && ctype_xdigit($token), 'Jeton aléatoire de 256 bits attendu.');
    $check(Csrf::token() === $token, 'Le jeton doit rester utilisable dans plusieurs onglets.');
    $check(Csrf::isValid($token), 'Le jeton de la session doit être accepté.');
    $check(session_status() === PHP_SESSION_NONE, 'Le verrou de session doit être libéré avant les appels IA.');

    foreach ([null, '', 'incorrect', [$token], 123] as $invalid) {
        $check(!Csrf::isValid($invalid), 'Un jeton absent ou mal formé doit être refusé.');
    }

    session_id('csrf-test-second');
    $check(!Csrf::isValid($token), 'Un jeton sans session correspondante doit être refusé.');
    $secondToken = Csrf::token();
    $check($secondToken !== $token && !Csrf::isValid($token), 'Les jetons doivent être propres à chaque session.');

    foreach (['index.php', 'analyze-ticket.php', 'validate-ticket.php', 'resolve-ticket.php'] as $endpoint) {
        foreach ([[], ['csrf_token' => 'incorrect'], ['csrf_token' => [$token]]] as $post) {
            $check($request($endpoint, 'POST', $post, $firstSessionId) === 403, "$endpoint : rejet attendu avant tout accès aux services.");
        }
        $check($request($endpoint, 'POST', ['csrf_token' => $secondToken], $firstSessionId) === 403, "$endpoint : jeton d'une autre session accepté.");
        if ($endpoint !== 'index.php') {
            $check($request($endpoint, 'POST', ['csrf_token' => $token, 'ticket_id' => 0], $firstSessionId) === 400, "$endpoint : un jeton valide doit permettre la validation des données.");
            foreach ([['1'], true, '4294967296', '1e2'] as $invalidId) {
                $check($request($endpoint, 'POST', ['csrf_token' => $token, 'ticket_id' => $invalidId], $firstSessionId) === 400, "$endpoint : identifiant mal formé accepté.");
            }
            $check($request($endpoint, 'GET', [], $firstSessionId) === 405, "$endpoint : GET doit être refusé.");
        }
    }

    foreach ([[], ['title' => ['texte'], 'description' => 'Texte conservé'], ['title' => 'Titre conservé', 'description' => ['texte']]] as $input) {
        $body = null;
        $check($request('index.php', 'POST', ['csrf_token' => $token, ...$input], $firstSessionId, $body) === 422, 'La création invalide doit afficher les erreurs sans connexion MySQL.');
        $check(str_contains($body, 'name="csrf_token"'), 'Le formulaire doit rester disponible après une erreur.');
    }
    $body = null;
    $check($request('index.php', 'POST', ['csrf_token' => $token, 'title' => '<Titre>', 'description' => ''], $firstSessionId, $body) === 422, 'Une description vide doit être refusée.');
    $check(str_contains($body, 'value="&lt;Titre&gt;"'), 'Le titre doit être conservé et échappé après une erreur.');
    $check($request('index.php', 'PUT', [], $firstSessionId) === 405, 'La création doit refuser PUT.');
    foreach ([['Messagerie'], 'Inconnue', true] as $invalidCategory) {
        $check($request('validate-ticket.php', 'POST', ['csrf_token' => $token, 'ticket_id' => '1', 'category' => $invalidCategory], $firstSessionId) === 400, 'La catégorie invalide doit être refusée avant la base.');
    }
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    foreach (glob($sessionDirectory . '/*') as $file) {
        unlink($file);
    }
    rmdir($sessionDirectory);
}

echo "OK : jetons de session, réutilisation, isolation et libération du verrou.\n";
echo "OK : protection des quatre traitements POST et contrôle des méthodes.\n";
echo "OK : formulaires mal formés refusés sans accès MySQL, valeurs conservées et échappées.\n";
