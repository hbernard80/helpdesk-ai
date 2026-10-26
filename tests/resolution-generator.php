<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\LlmClient;
use App\Services\ResolutionGenerator;

$llm = new class extends LlmClient {
    public array $response = [];
    public array $messages = [];

    /**
     * Simule Ollama et vérifie le budget ainsi que le schéma des références autorisées.
     *
     * @param list<array{role: string, content: string}> $messages Conversation à mémoriser.
     * @param int $maxTokens Budget de génération à vérifier.
     * @param array<string, mixed>|string $format Schéma JSON attendu par le générateur.
     * @return array<string, mixed> Réponse configurée pour le scénario de test.
     * @throws LogicException Si le budget ou le schéma ne respecte pas le contrat.
     */
    public function chat(array $messages, int $maxTokens = 150, array|string $format = 'json'): array
    {
        $this->messages = $messages;
        if ($maxTokens < 500) {
            throw new LogicException('Budget de génération insuffisant.');
        }
        if (!is_array($format) || $format['properties']['steps']['items']['properties']['sources']['items']['enum'] !== ['PROC-004-messagerie.md']) {
            throw new LogicException('Schéma ou références autorisées manquants.');
        }
        return $this->response;
    }
};
$generator = new ResolutionGenerator($llm);
$procedures = [['source' => 'PROC-004-messagerie.md', 'content' => 'Vérifier la connexion réseau.', 'score' => 0.7]];
$valid = [
    'summary' => 'Vérifier la messagerie.',
    'steps' => [['instruction' => 'Vérifier la connexion réseau.', 'sources' => ['PROC-004-messagerie.md']]],
    'limitations' => '',
];
/**
 * Prépare la réponse JSON du faux client Ollama.
 *
 * @param array<string, mixed> $result Proposition à encoder dans le message simulé.
 * @return void
 * @throws JsonException Si l'encodage échoue.
 */
$setResponse = static function (array $result) use ($llm): void {
    $llm->response = ['message' => ['content' => json_encode($result, JSON_THROW_ON_ERROR)]];
};
/**
 * Vérifie qu'un scénario est rejeté par une exception attendue.
 *
 * @param callable(): mixed $action Scénario à exécuter.
 * @param string $label Libellé du scénario dans les résultats.
 * @return void
 * @throws LogicException Si le scénario est accepté à tort.
 */
$mustReject = static function (callable $action, string $label): void {
    try {
        $action();
    } catch (RuntimeException | InvalidArgumentException | JsonException $exception) {
        echo "OK : $label\n";
        return;
    }
    throw new LogicException("Réponse incorrectement acceptée : $label");
};

$setResponse($valid);
$result = $generator->generate('Outlook', 'Envoi impossible.', $procedures);
if ($result['sources'] !== ['PROC-004-messagerie.md']) {
    throw new LogicException('Références incorrectes.');
}
$prompt = json_decode($llm->messages[1]['content'], true, 512, JSON_THROW_ON_ERROR);
if ($prompt['procedures'][0]['content'] !== $procedures[0]['content']) {
    throw new LogicException('Le contenu documentaire manque dans le prompt.');
}
echo "OK : réponse valide et contenu documentaire transmis\n";

$invalid = $valid;
$invalid['steps'][0]['sources'] = ['procedure-inventee.md'];
$setResponse($invalid);
$mustReject(fn() => $generator->generate('Outlook', 'Envoi impossible.', $procedures), 'référence inventée');
$invalid['steps'][0]['sources'] = [];
$setResponse($invalid);
$mustReject(fn() => $generator->generate('Outlook', 'Envoi impossible.', $procedures), 'étape sans référence');
$mustReject(fn() => $generator->generate('Outlook', 'Envoi impossible.', []), 'aucune procédure');
$llm->response = ['done_reason' => 'length', 'message' => ['content' => '{}']];
$mustReject(fn() => $generator->generate('Outlook', 'Envoi impossible.', $procedures), 'réponse tronquée');
$llm->response = ['message' => ['content' => 'JSON invalide']];
$mustReject(fn() => $generator->generate('Outlook', 'Envoi impossible.', $procedures), 'JSON invalide');
$setResponse(['summary' => 'Documentation insuffisante.', 'steps' => [], 'limitations' => 'Escalader au support.']);
$result = $generator->generate('Autre incident', 'Inconnu.', $procedures);
if ($result['steps'] !== [] || $result['sources'] !== []) {
    throw new LogicException('Abstention incorrecte.');
}
echo "OK : abstention avec limites explicites\n";
