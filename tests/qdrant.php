<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\QdrantStore;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new LogicException($message);
    }
};
$ok = static fn(mixed $result): Response => new Response(200, [], json_encode(['status' => 'ok', 'result' => $result], JSON_THROW_ON_ERROR));
$history = [];
$mock = new MockHandler();
$stack = HandlerStack::create($mock);
$stack->push(Middleware::history($history));
$store = new QdrantStore(collection: 'test_procedures', model: 'test-model', http: new Client([
    'base_uri' => 'http://qdrant:6333/', 'handler' => $stack, 'http_errors' => false,
]));
$procedures = [['source' => 'mail.md', 'content' => 'Réparer Outlook', 'embedding' => [1, 0]]];
$mock->append($ok(['aliases' => []]), $ok(true), $ok(['status' => 'completed']), $ok(true));
$store->replace($procedures);
$decode = static function (int $i) use (&$history): array {
    return json_decode((string) $history[$i]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
};
$creation = $decode(1);
$vectorName = array_key_first($creation['vectors']);
$check($creation['vectors'][$vectorName] === ['size' => 2, 'distance' => 'Cosine'], 'Configuration des vecteurs.');
$check($decode(2)['points'][0]['payload']['source'] === 'mail.md', 'Sources conservées.');
$check($history[2]['request']->getUri()->getQuery() === 'wait=true', 'Attendre les écritures avant bascule.');
$alias = $decode(3)['actions'][0]['create_alias'];
$check($alias['alias_name'] === 'test_procedures', 'Alias de lecture stable.');

$mock->append($ok(['aliases' => [['alias_name' => 'test_procedures', 'collection_name' => $alias['collection_name']]]]), $ok(true), $ok(true), $ok(true));
$store->replace($procedures);
$actions = $decode(7)['actions'];
$check(isset($actions[0]['delete_alias'], $actions[1]['create_alias']), 'Bascule atomique lors de la réindexation.');
$check($actions[1]['create_alias']['collection_name'] !== $alias['collection_name'], 'Chaque index utilise une collection distincte.');

$mock->append($ok(['points' => [['score' => 0.9, 'payload' => ['source' => 'mail.md', 'content' => 'Réparer Outlook', 'embedding_model' => 'test-model']]]]));
$results = $store->search([1, 0], 3);
$check($results[0] === ['source' => 'mail.md', 'content' => 'Réparer Outlook', 'score' => 0.9], 'Contrat du générateur RAG.');
$check($decode(8)['using'] === $vectorName && $decode(8)['limit'] === 3, 'Même modèle et limite en recherche.');

foreach ([new Response(503), new Response(200, [], '{'), $ok(['points' => [['score' => 0.5, 'payload' => []]]])] as $response) {
    $mock->append($response);
    try {
        $store->search([1, 0]);
        throw new LogicException('Une réponse invalide doit être refusée.');
    } catch (RuntimeException) {
    }
}
$before = count($history);
foreach ([[], [['source' => 'bad.md', 'content' => 'Texte', 'embedding' => [0, 0]]], array_merge($procedures, [['source' => 'other.md', 'content' => 'Texte', 'embedding' => [1, 0, 0]]])] as $invalid) {
    try {
        $store->replace($invalid);
        throw new LogicException('Index invalide accepté.');
    } catch (RuntimeException | InvalidArgumentException) {
    }
}
$check(count($history) === $before, 'Corpus invalide : aucun appel ni modification distante.');
$mock->append($ok(['aliases' => []]), $ok(true), new Response(500));
try {
    $store->replace($procedures);
    throw new LogicException('Échec de chargement ignoré.');
} catch (RuntimeException) {
}
$check(count($history) === $before + 3, 'Échec de chargement : aucune bascule de l’alias.');
$check($store->search([1, 0], 0) === [], 'Limite nulle sans appel réseau.');
echo "OK : Qdrant, collection, chargement, alias atomique, recherche, erreurs et conservation de l’index.\n";
