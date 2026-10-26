<?php
declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/** Stockage des procédures dans Qdrant via son API REST. */
final class QdrantStore
{
    private Client $http;
    private string $collection;
    private string $vectorName;
    private string $model;

    public function __construct(?string $url = null, ?string $collection = null, ?string $model = null, int $timeout = 30, ?Client $http = null)
    {
        $this->collection = $collection ?? Config::get('qdrant.collection');
        if (preg_match('/^[a-zA-Z0-9_-]+$/D', $this->collection) !== 1) {
            throw new \InvalidArgumentException('Nom de collection Qdrant invalide.');
        }
        $this->model = $model ?? Config::get('ollama.embedding_model');
        // Un autre modèle, même de dimension identique, doit nécessiter une réindexation.
        $this->vectorName = 'embedding_' . hash('sha256', $this->model);
        $this->http = $http ?? new Client([
            'base_uri' => rtrim($url ?? Config::get('qdrant.url'), '/') . '/',
            'timeout' => $timeout,
            'connect_timeout' => min(5, $timeout),
            'allow_redirects' => false,
            'http_errors' => false,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /** Construit une nouvelle collection puis bascule l'alias atomiquement. */
    public function replace(array $procedures): void
    {
        if ($procedures === []) {
            throw new RuntimeException('Corpus vide : index Qdrant existant conservé.');
        }
        $points = [];
        $dimensions = null;
        foreach ($procedures as $procedure) {
            if (!is_string($procedure['source'] ?? null) || !is_string($procedure['content'] ?? null)) {
                throw new RuntimeException('Procédure invalide pour Qdrant.');
            }
            $vector = EmbeddingVector::validate($procedure['embedding'] ?? null, $dimensions);
            $dimensions ??= count($vector);
            $points[] = [
                'id' => count($points) + 1,
                'vector' => [$this->vectorName => $vector],
                'payload' => [
                    'source' => $procedure['source'],
                    'content' => $procedure['content'],
                    'embedding_model' => $this->model,
                ],
            ];
        }

        $aliases = $this->request('GET', 'aliases')['aliases'] ?? null;
        if (!is_array($aliases)) {
            throw new RuntimeException('Liste des alias Qdrant invalide.');
        }
        $exists = false;
        foreach ($aliases as $alias) {
            $exists = $exists || ($alias['alias_name'] ?? null) === $this->collection;
        }
        $name = $this->collection . '_' . bin2hex(random_bytes(8));
        $this->request('PUT', 'collections/' . $name, [
            'vectors' => [$this->vectorName => ['size' => $dimensions, 'distance' => 'Cosine']],
        ]);
        // En cas de réponse réseau perdue lors de la bascule, conserver les collections :
        // l'alias peut déjà pointer vers la nouvelle version.
        foreach (array_chunk($points, 64) as $batch) {
            $this->request('PUT', 'collections/' . $name . '/points?wait=true', ['points' => $batch]);
        }
        $actions = [];
        if ($exists) {
            $actions[] = ['delete_alias' => ['alias_name' => $this->collection]];
        }
        $actions[] = ['create_alias' => ['alias_name' => $this->collection, 'collection_name' => $name]];
        $this->request('POST', 'collections/aliases', ['actions' => $actions]);
    }

    /** @return list<array{source: string, content: string, score: float}> */
    public function search(array $vector, int $limit = 3): array
    {
        if ($limit <= 0) {
            return [];
        }
        EmbeddingVector::validate($vector);
        $result = $this->request('POST', 'collections/' . $this->collection . '/points/query', [
            'query' => $vector,
            'using' => $this->vectorName,
            'limit' => $limit,
            'with_payload' => true,
            'with_vector' => false,
        ]);
        $points = $result['points'] ?? null;
        if (!is_array($points) || !array_is_list($points)) {
            throw new RuntimeException('Résultats Qdrant invalides.');
        }
        $results = [];
        foreach ($points as $point) {
            $payload = $point['payload'] ?? [];
            $score = $point['score'] ?? null;
            if (!is_string($payload['source'] ?? null) || !is_string($payload['content'] ?? null)
                || (!is_float($score) && !is_int($score)) || !is_finite((float) $score)
                || ($payload['embedding_model'] ?? null) !== $this->model
            ) {
                throw new RuntimeException('Procédure Qdrant invalide ou modèle incompatible. Réindexez le corpus.');
            }
            $results[] = ['source' => $payload['source'], 'content' => $payload['content'], 'score' => (float) $score];
        }
        return $results;
    }

    /** Valide HTTP et l'enveloppe Qdrant, sans exposer le corps distant. */
    private function request(string $method, string $path, ?array $body = null): mixed
    {
        try {
            $response = $this->http->request($method, $path, $body === null ? [] : ['json' => $body]);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new RuntimeException('Erreur Qdrant HTTP ' . $response->getStatusCode() . ' : vérifiez le service, la collection et le modèle ; relancez l’indexation si nécessaire.');
            }
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || ($data['status'] ?? null) !== 'ok' || !array_key_exists('result', $data)) {
                throw new RuntimeException('Réponse Qdrant invalide.');
            }
            return $data['result'];
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Communication avec Qdrant impossible.', 0, $exception);
        } catch (\JsonException $exception) {
            throw new RuntimeException('JSON Qdrant invalide.', 0, $exception);
        }
    }
}
