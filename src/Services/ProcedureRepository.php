<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ProcedureRepository
{
    /**
     * Configure le répertoire contenant les procédures.
     *
     * @param string $directory Chemin du répertoire des fichiers Markdown.
     */
    public function __construct(
        private string $directory
    ) {
    }

    /**
     * Lit les fichiers .md directement présents dans le répertoire configuré.
     *
     * @return list<array{source: string, content: string}> Noms de fichiers et textes complets.
     * @throws RuntimeException Si le répertoire manque ou si un fichier est illisible.
     */
    public function findAll(): array
    {
        if (!is_dir($this->directory)) {
            throw new RuntimeException(
                'Répertoire des procédures introuvable : '
                . $this->directory
            );
        }

        $procedures = [];

        foreach (glob($this->directory . '/*.md') ?: [] as $file) {
            $content = file_get_contents($file);

            if ($content === false) {
                throw new RuntimeException(
                    'Impossible de lire : ' . $file
                );
            }

            $procedures[] = [
                'source' => basename($file),
                'content' => $content
            ];
        }

        return $procedures;
    }
}
