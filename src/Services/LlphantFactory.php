<?php
declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use LLPhant\OllamaConfig;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/** Configuration LLPhant et contrôles communs des réponses du fournisseur. */
final class LlphantFactory
{
    public static function config(?string $url, string $model, int $timeout): OllamaConfig
    {
        if ($timeout < 1) {
            throw new \InvalidArgumentException('Le délai HTTP doit être strictement positif.');
        }
        $config = new OllamaConfig();
        $config->url = rtrim($url ?? Config::get('ollama.url'), '/') . '/api/';
        $config->model = $model;
        $config->timeout = $timeout;

        return $config;
    }

    public static function http(OllamaConfig $config): Client
    {
        $stack = HandlerStack::create();
        $stack->push(Middleware::mapResponse(static function (ResponseInterface $response): ResponseInterface {
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException("Erreur HTTP Ollama ($status).");
            }
            $body = (string) $response->getBody();
            try {
                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new RuntimeException('Réponse JSON Ollama invalide.', 0, $exception);
            }
            if (!is_array($data) || !str_starts_with(ltrim($body), '{')) {
                throw new RuntimeException('Format de réponse Ollama inattendu : objet JSON attendu.');
            }
            if (array_key_exists('error', $data)) {
                $error = is_string($data['error']) ? $data['error'] : 'erreur non détaillée';
                throw new RuntimeException('Erreur signalée par Ollama : ' . $error);
            }
            if (array_key_exists('embeddings', $data)) {
                $vectors = $data['embeddings'];
                if (!is_array($vectors) || !array_is_list($vectors) || count($vectors) !== 1) {
                    throw new RuntimeException('Embedding invalide : un unique vecteur est attendu.');
                }
                try {
                    EmbeddingVector::validate($vectors[0]);
                } catch (\InvalidArgumentException $exception) {
                    throw new RuntimeException('Embedding invalide : ' . $exception->getMessage(), 0, $exception);
                }
            } elseif (!is_string($data['message']['content'] ?? null)) {
                throw new RuntimeException('Réponse du modèle invalide : message ou embedding absent.');
            }

            $response->getBody()->rewind();

            return $response;
        }));

        return new Client([
            'base_uri' => $config->url,
            'handler' => $stack,
            'headers' => ['Accept' => 'application/json'],
            'timeout' => $config->timeout,
            'connect_timeout' => min(10, $config->timeout),
            'allow_redirects' => false,
            'http_errors' => false,
        ]);
    }
}
