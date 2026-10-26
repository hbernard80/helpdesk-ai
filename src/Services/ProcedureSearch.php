<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

final class ProcedureSearch
{
    /**
     * Configure la recherche dans un index vectoriel existant.
     *
     * @param EmbeddingClient $embeddings Service de vectorisation des requêtes.
     * @param string|QdrantStore $indexPath Qdrant par défaut ; chemin JSON explicite pour compatibilité.
     */
    public function __construct(
        private EmbeddingClient $embeddings,
        private string|QdrantStore $indexPath = new QdrantStore()
    ) {
    }

    /**
     * Classe les procédures par similarité cosinus décroissante avec la requête.
     *
     * @param string $query Texte décrivant le problème recherché.
     * @param int $limit Nombre maximal de résultats ; zéro ou moins renvoie une liste vide.
     * @return list<array{source: string, content: string, score: float}> Procédures les plus proches.
     * @throws RuntimeException Si l'index est absent, illisible, invalide ou si Ollama échoue.
     * @throws \JsonException Si l'index contient du JSON invalide.
     * @throws InvalidArgumentException Si un vecteur de l'index est invalide ou incompatible.
     */
    public function search(string $query, int $limit = 3): array
    {
        if ($limit <= 0) {
            return [];
        }
        if ($this->indexPath instanceof QdrantStore) {
            return $this->indexPath->search($this->embeddings->embed($query), $limit);
        }

        if (!is_file($this->indexPath)) {
            throw new RuntimeException(
                'Index introuvable. Lancez l’indexation.'
            );
        }

        $json = file_get_contents($this->indexPath);

        if ($json === false) {
            throw new RuntimeException(
                'Impossible de lire l’index.'
            );
        }

        $procedures = json_decode(
            $json,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($procedures) || !array_is_list($procedures) || !str_starts_with(ltrim($json), '[')) {
            throw new RuntimeException('Index invalide.');
        }

        $dimensions = null;
        foreach ($procedures as $procedure) {
            if (!is_array($procedure)
                || !is_string($procedure['source'] ?? null)
                || !is_string($procedure['content'] ?? null)
            ) {
                throw new RuntimeException('Entrée de l’index invalide. Reconstruisez l’index.');
            }
            $vector = EmbeddingVector::validate($procedure['embedding'] ?? null, $dimensions);
            $dimensions ??= count($vector);
        }

        // Un seul embedding pour la recherche utilisateur
        $queryVector = $this->embeddings->embed($query);
        EmbeddingVector::validate($queryVector, $dimensions);

        $results = [];

        foreach ($procedures as $procedure) {
            $results[] = [
                'source' => $procedure['source'],
                'content' => $procedure['content'],
                'score' => $this->cosineSimilarity(
                    $queryVector,
                    $procedure['embedding']
                )
            ];
        }

        usort(
            $results,
            fn(array $a, array $b) =>
                $b['score'] <=> $a['score']
        );

        return array_slice(
            $results,
            0,
            max(0, $limit)
        );
    }

    /**
     * Calcule la similarité cosinus de deux vecteurs de même dimension.
     *
     * Les composantes sont mises à l'échelle pour éviter les débordements numériques.
     *
     * @param list<float|int> $a Premier vecteur validé et non nul.
     * @param list<float|int> $b Second vecteur validé et non nul.
     * @return float Similarité finie comprise entre -1 et 1.
     * @throws InvalidArgumentException Si les vecteurs sont vides ou de tailles différentes.
     */
    private function cosineSimilarity(
        array $a,
        array $b
    ): float {
        if (count($a) !== count($b) || $a === []) {
            throw new InvalidArgumentException(
                'Vecteurs incompatibles.'
            );
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $scaleA = max(array_map(abs(...), $a));
        $scaleB = max(array_map(abs(...), $b));

        foreach ($a as $i => $value) {
            $scaledA = $value / $scaleA;
            $scaledB = $b[$i] / $scaleB;
            $dot += $scaledA * $scaledB;
            $normA += $scaledA ** 2;
            $normB += $scaledB ** 2;
        }

        return max(-1.0, min(1.0, $dot / (sqrt($normA) * sqrt($normB))));
    }
}
