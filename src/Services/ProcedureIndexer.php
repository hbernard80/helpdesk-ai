<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ProcedureIndexer
{
    /**
     * Configure les sources et la destination de l'index vectoriel.
     *
     * @param ProcedureRepository $repository Lecteur des procédures Markdown.
     * @param EmbeddingClient $embeddings Service de vectorisation.
     * @param string|QdrantStore $indexPath Qdrant par défaut ; chemin JSON explicite pour compatibilité.
     */
    public function __construct(
        private ProcedureRepository $repository,
        private EmbeddingClient $embeddings,
        private string|QdrantStore $indexPath = new QdrantStore()
    ) {
    }

    /**
     * Vectorise toutes les procédures et publie un nouvel index complet.
     *
     * @return int Nombre de procédures indexées.
     * @throws RuntimeException Si la lecture, la vectorisation ou l'écriture échoue.
     * @throws \JsonException Si l'encodage de l'index ou un échange JSON échoue.
     */
    public function build(): int
    {
        $index = [];
        $dimensions = null;

        foreach ($this->repository->findAll() as $procedure) {
            $vector = $this->embeddings->embed($procedure['content']);
            try {
                EmbeddingVector::validate($vector, $dimensions);
            } catch (\InvalidArgumentException $exception) {
                throw new RuntimeException('Indexation impossible : ' . $exception->getMessage(), 0, $exception);
            }
            $dimensions ??= count($vector);

            $index[] = [
                'source' => $procedure['source'],
                'content' => $procedure['content'],
                'embedding' => $vector,
            ];
        }

        if ($this->indexPath instanceof QdrantStore) {
            $this->indexPath->replace($index);
            return count($index);
        }

        $json = json_encode(
            $index,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (file_put_contents(
            $this->indexPath,
            $json,
            LOCK_EX
        ) === false) {
            throw new RuntimeException(
                'Impossible d’enregistrer l’index.'
            );
        }

        return count($index);
    }
}
