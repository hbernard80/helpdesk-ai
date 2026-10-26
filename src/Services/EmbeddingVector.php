<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class EmbeddingVector
{
    /**
     * Vérifie qu'un vecteur peut être utilisé pour calculer une similarité cosinus.
     *
     * @param mixed $vector Valeur reçue d'Ollama ou lue dans l'index JSON.
     * @param int|null $dimensions Nombre de composantes attendu, si connu.
     * @return list<float|int> Vecteur non vide, numérique, fini et non nul.
     * @throws InvalidArgumentException Si la structure, les valeurs ou la dimension sont invalides.
     */
    public static function validate(mixed $vector, ?int $dimensions = null): array
    {
        if (!is_array($vector) || $vector === [] || !array_is_list($vector)) {
            throw new InvalidArgumentException('Vecteur non vide sous forme de liste attendu.');
        }
        if ($dimensions !== null && count($vector) !== $dimensions) {
            throw new InvalidArgumentException('Dimensions de vecteurs incompatibles. Reconstruisez l’index avec le modèle configuré.');
        }

        $nonZero = false;
        foreach ($vector as $component) {
            if ((!is_int($component) && !is_float($component)) || !is_finite((float) $component)) {
                throw new InvalidArgumentException('Composantes numériques finies attendues.');
            }
            $nonZero = $nonZero || $component != 0;
        }
        if (!$nonZero) {
            throw new InvalidArgumentException('Un vecteur entièrement nul ne permet pas de calculer une similarité.');
        }

        return $vector;
    }
}
