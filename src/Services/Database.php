<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    /**
     * Retourne la connexion MySQL partagée, créée depuis les variables DB_*.
     *
     * @return PDO Connexion UTF-8 avec résultats associatifs et exceptions activées.
     * @throws RuntimeException Si le mot de passe manque ou si la connexion échoue.
     */
    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = Config::get('db.host');
        $name = Config::get('db.name');
        $user = Config::get('db.user');
        $password = Config::get('db.password');

        if ($password === '') {
            throw new RuntimeException(
                'La variable DB_PASSWORD est manquante.'
            );
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $host,
            $name
        );

        try {
            self::$connection = new PDO(
                $dsn,
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            return self::$connection;

        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Impossible de se connecter à MySQL.',
                0,
                $exception
            );
        }
    }
}
