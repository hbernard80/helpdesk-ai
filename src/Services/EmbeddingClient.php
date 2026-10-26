<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class EmbeddingClient
{
    private \LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator $generator;

    /**
     * Configure le service de vectorisation Ollama.
     *
     * @param string|null $url URL de base, ou null pour utiliser la configuration commune.
     * @param string|null $model Modèle d'embedding, ou null pour utiliser la configuration commune.
     */
    public function __construct(
        ?string $url = null,
        ?string $model = null,
        int $timeout = 120
    ) {
        $config = LlphantFactory::config($url, $model ?? Config::get('ollama.embedding_model'), $timeout);
        $this->generator = new \LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator(
            $config,
            LlphantFactory::http($config)
        );
    }

    /**
     * Demande au modèle le vecteur représentant un texte.
     *
     * @param string $text Texte à vectoriser.
     * @return list<float|int> Unique vecteur renvoyé pour le texte, numérique, fini et non nul.
     * @throws RuntimeException En cas d'erreur réseau, HTTP, JSON ou de vecteur invalide.
     */
    public function embed(string $text): array
    {
        try {
            json_encode($text, JSON_THROW_ON_ERROR);
            return EmbeddingVector::validate($this->generator->embedText($text));
        } catch (\JsonException $exception) {
            throw new RuntimeException('Erreur de sérialisation JSON pour Ollama.', 0, $exception);
        } catch (\GuzzleHttp\Exception\GuzzleException $exception) {
            throw new RuntimeException('Erreur de communication avec Ollama via LLPhant.', 0, $exception);
        } catch (\InvalidArgumentException $exception) {
            throw new RuntimeException('Embedding invalide : ' . $exception->getMessage(), 0, $exception);
        }
    }
}
