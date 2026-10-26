<?php
declare(strict_types=1);

namespace App\Services;

final class Utils
{
    /**
     * Échappe une valeur pour un contenu HTML ou un attribut entre guillemets.
     *
     * @param string $value Texte brut à afficher.
     * @return string Texte échappé en UTF-8, avec remplacement des séquences invalides.
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
