<?php
declare(strict_types=1);

namespace App\Services;

final class TicketCategories
{
    /** @var list<string> Catégories autorisées, dans l'ordre d'affichage. */
    public const ALL = [
        'Authentification',
        'Messagerie',
        'Réseau',
        'Matériel',
        'Applications métier',
        'Autre',
    ];

    /**
     * Vérifie qu'une valeur correspond exactement à une catégorie autorisée.
     *
     * @param mixed $category Valeur issue d'un formulaire ou d'une réponse IA.
     * @return bool Vrai si la valeur est une chaîne présente dans la liste.
     */
    public static function isValid(mixed $category): bool
    {
        return is_string($category) && in_array($category, self::ALL, true);
    }
}
