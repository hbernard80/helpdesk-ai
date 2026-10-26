<?php
declare(strict_types=1);

namespace App\Services;

final class Csrf
{
    /**
     * Crée ou récupère le jeton de session, puis libère le verrou de session.
     *
     * @return string Jeton aléatoire encodé en hexadécimal sur 64 caractères.
     * @throws \RuntimeException Si la session ne peut pas démarrer.
     * @throws \Random\RandomException Si la génération aléatoire échoue.
     */
    public static function token(): string
    {
        self::startSession();

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $token = $_SESSION['csrf_token'];
        session_write_close();

        return $token;
    }

    /**
     * Compare le jeton reçu à celui de la session en temps constant.
     *
     * @param mixed $token Valeur reçue du formulaire, éventuellement mal formée.
     * @return bool Vrai si le jeton correspond à la session courante.
     * @throws \RuntimeException Si la session ne peut pas démarrer.
     */
    public static function isValid(mixed $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        self::startSession();
        $expected = $_SESSION['csrf_token'] ?? null;
        session_write_close();

        return is_string($expected) && hash_equals($expected, $token);
    }

    /**
     * Vérifie le champ POST csrf_token et interrompt la requête en HTTP 403 si nécessaire.
     *
     * @return void
     * @throws \RuntimeException Si la session ne peut pas démarrer.
     */
    public static function requireValidToken(): void
    {
        if (!self::isValid($_POST['csrf_token'] ?? null)) {
            Http::abort(403, 'Jeton de sécurité invalide ou expiré. Rechargez la page des tickets puis réessayez.');
        }
    }

    /**
     * Ouvre une session avec des cookies protégés si aucune session n'est active.
     *
     * @return void
     * @throws \RuntimeException Si PHP ne parvient pas à ouvrir la session.
     */
    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!session_start([
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'cookie_httponly' => true,
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'cookie_samesite' => 'Lax',
        ])) {
            throw new \RuntimeException('Impossible de démarrer la session.');
        }
    }
}
