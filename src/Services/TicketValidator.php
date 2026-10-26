<?php
declare(strict_types=1);

namespace App\Services;

final class TicketValidator
{
    public const TITLE_MAX_LENGTH = 255;
    public const DESCRIPTION_MAX_LENGTH = 30000;
    public const DESCRIPTION_MAX_BYTES = 65535;
    public const ID_MAX = 4294967295;

    /**
     * Normalise les champs de création et vérifie leur compatibilité avec le schéma SQL.
     *
     * @param array<string, mixed> $input Champs reçus du formulaire.
     * @return array{title: string, description: string, errors: array<string, string>} Valeurs réaffichables et erreurs par champ.
     */
    public static function creation(array $input): array
    {
        $values = ['title' => '', 'description' => ''];
        $errors = [];

        foreach ($values as $field => $_) {
            $value = $input[$field] ?? null;
            $label = $field === 'title' ? 'Le titre' : 'La description';

            if (!is_string($value) || preg_match('//u', $value) !== 1 || str_contains($value, "\0")) {
                $errors[$field] = $label . ' doit être un texte UTF-8 valide.';
                continue;
            }

            $value = trim($value);
            $values[$field] = $value;
            $limit = $field === 'title' ? self::TITLE_MAX_LENGTH : self::DESCRIPTION_MAX_LENGTH;

            if (preg_match('/\S/u', $value) !== 1) {
                $errors[$field] = $label . ' est obligatoire.';
            } elseif (mb_strlen($value, 'UTF-8') > $limit) {
                $errors[$field] = $label . ' ne doit pas dépasser ' . $limit . ' caractères.';
            } elseif ($field === 'description' && strlen($value) > self::DESCRIPTION_MAX_BYTES) {
                $errors[$field] = 'La description dépasse la capacité de stockage de 65 535 octets. Réduisez sa longueur.';
            }
        }

        return [...$values, 'errors' => $errors];
    }

    /**
     * Vérifie un identifiant décimal positif compatible avec INT UNSIGNED.
     *
     * @param mixed $value Identifiant reçu du formulaire.
     * @return int|null Identifiant validé, ou null si sa forme ou sa valeur est invalide.
     */
    public static function ticketId(mixed $value): ?int
    {
        if (!is_string($value) || preg_match('/\A[1-9][0-9]{0,9}\z/', $value) !== 1) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => self::ID_MAX],
        ]);

        return $id === false ? null : $id;
    }

    /**
     * Vérifie la catégorie sélectionnée sans convertir les types reçus.
     *
     * @param mixed $value Catégorie reçue du formulaire.
     * @return string|null Catégorie autorisée, ou null si elle est invalide.
     */
    public static function category(mixed $value): ?string
    {
        return TicketCategories::isValid($value) ? $value : null;
    }
}
