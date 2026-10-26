<?php
declare(strict_types=1);

namespace App\Services;

final class Config
{
    /** @var array<string, string>|null Configuration chargée une fois par exécution. */
    private static ?array $values = null;

    /**
     * Retourne une valeur de la configuration commune, après application de l'environnement.
     *
     * @param string $key Clé définie dans src/config.php.
     * @return string Valeur configurée.
     * @throws \InvalidArgumentException Si la clé n'existe pas.
     */
    public static function get(string $key): string
    {
        self::$values ??= require __DIR__ . '/../config.php';

        if (!array_key_exists($key, self::$values)) {
            throw new \InvalidArgumentException('Clé de configuration inconnue : ' . $key);
        }

        return self::$values[$key];
    }
}
