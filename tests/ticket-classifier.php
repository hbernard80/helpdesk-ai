<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\LlmClient;
use App\Services\TicketCategories;
use App\Services\TicketClassifier;

$llm = new class extends LlmClient {
    public array $response = [];

    public function chat(array $messages, int $maxTokens = 150, array|string $format = 'json'): array
    {
        if ($maxTokens !== 300 || !is_array($format)
            || $format['required'] !== ['category', 'summary']
            || $format['properties']['category']['enum'] !== TicketCategories::ALL
            || $format['properties']['summary']['minLength'] !== 1
            || $format['additionalProperties'] !== false
        ) {
            throw new LogicException('Schéma de classification ou budget incorrect.');
        }
        $ticket = json_decode($messages[1]['content'], true, 512, JSON_THROW_ON_ERROR);
        if ($ticket !== ['title' => 'Outlook', 'description' => 'Envoi impossible.']) {
            throw new LogicException('Ticket incorrectement transmis.');
        }
        return $this->response;
    }
};
$classifier = new TicketClassifier($llm);
$classify = static fn(): array => $classifier->classify('Outlook', 'Envoi impossible.');

foreach (TicketCategories::ALL as $category) {
    $llm->response = ['message' => ['content' => json_encode([
        'category' => $category, 'summary' => ' Résumé du ticket. ',
    ], JSON_THROW_ON_ERROR)]];
    if ($classify() !== ['category' => $category, 'summary' => 'Résumé du ticket.']) {
        throw new LogicException('Classification valide refusée ou mal normalisée.');
    }
}
$llm->response = ['message' => ['content' => "```json\n{\"category\":\"Messagerie\",\"summary\":\"Résumé\"}\n```"]];
if ($classify()['category'] !== 'Messagerie') {
    throw new LogicException('Compatibilité des balises Markdown perdue.');
}

$invalidResponses = [
    [],
    ['message' => ['content' => null]],
    ['message' => ['content' => []]],
    ['message' => ['content' => '']],
    ['message' => ['content' => '{']],
    ['message' => ['content' => '{"category":"Imprimante","summary":"Résumé"}']],
    ['message' => ['content' => '{"category":"Messagerie"}']],
    ['message' => ['content' => '{"category":"Messagerie","summary":42}']],
    ['message' => ['content' => '{"category":"Messagerie","summary":"   "}']],
    ['message' => ['content' => '{"category":[],"summary":"Résumé"}']],
    ['message' => ['content' => 'null']],
    ['done_reason' => 'length', 'message' => ['content' => '{"category":"Messagerie","summary":"Résumé"}']],
];
foreach ($invalidResponses as $response) {
    $llm->response = $response;
    try {
        $classify();
    } catch (RuntimeException | JsonException $exception) {
        continue;
    }
    throw new LogicException('Réponse de classification invalide acceptée.');
}

echo "OK : schéma et budget de classification, catégories, résumé, réponses absentes, invalides et tronquées.\n";
