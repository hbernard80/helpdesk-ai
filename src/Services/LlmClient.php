<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class LlmClient
{
    private LlphantOllamaChat $client;

    /**
     * Configure LLPhant, le serveur et le modèle utilisés pour les générations.
     *
     * @param string|null $baseUrl URL de base, ou null pour utiliser la configuration commune.
     * @param string|null $model Modèle de génération, ou null pour utiliser la configuration commune.
     */
    public function __construct(?string $baseUrl = null, ?string $model = null, int $timeout = 180)
    {
        $config = LlphantFactory::config($baseUrl, $model ?? Config::get('ollama.model'), $timeout);
        $this->client = new LlphantOllamaChat($config);
        $this->client->client = LlphantFactory::http($config);
    }

    /**
     * Envoie une conversation au modèle via LLPhant.
     *
     * @param list<array{role: string, content: string}> $messages Conversation à transmettre.
     * @param int $maxTokens Budget maximal de tokens générés.
     * @param array<string, mixed>|string $format Format demandé : JSON ou schéma JSON.
     * @return array<string, mixed> Réponse complète de l'API, métadonnées incluses.
     * @throws RuntimeException Si la requête échoue ou si la réponse est invalide.
     */
    public function chat(array $messages, int $maxTokens = 150, array|string $format = 'json'): array
    {
        $this->client->setModelOption('think', false);
        $this->client->setModelOption('format', $format);
        $this->client->setModelOption('options', ['num_predict' => $maxTokens, 'temperature' => 0]);
        try {
            json_encode($messages, JSON_THROW_ON_ERROR);
            $this->client->generateChat(array_map(\LLPhant\Chat\Message::fromJson(...), $messages));
        } catch (\JsonException $exception) {
            throw new RuntimeException('Erreur de sérialisation JSON pour Ollama.', 0, $exception);
        } catch (\GuzzleHttp\Exception\GuzzleException $exception) {
            throw new RuntimeException('Erreur de communication avec Ollama via LLPhant.', 0, $exception);
        } catch (\GuzzleHttp\Exception\InvalidArgumentException $exception) {
            throw new RuntimeException('Erreur de sérialisation JSON pour Ollama.', 0, $exception);
        }

        return $this->client->response;
    }

    /**
     * Génère une réponse à partir d'un prompt.
     *
     * @param string $prompt Consigne à transmettre au modèle.
     * @return array<string, mixed> Réponse complète de l'API.
     * @throws RuntimeException Si la requête échoue ou si la réponse est invalide.
     */
    public function generate(string $prompt): array
    {
        $response = $this->chat([['role' => 'user', 'content' => $prompt]]);

        return [...$response, 'response' => $response['message']['content']];
    }
}
