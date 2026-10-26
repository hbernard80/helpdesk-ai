<?php
declare(strict_types=1);

namespace App\Services;

final class Http
{
    /**
     * Refuse les méthodes non autorisées avec HTTP 405 et l'en-tête Allow.
     *
     * @param list<string> $methods Méthodes autorisées, en majuscules.
     * @param bool $json Vrai pour retourner les erreurs au format JSON.
     * @return void
     */
    public static function requireMethods(array $methods, bool $json = false): void
    {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $methods, true)) {
            header('Allow: ' . implode(', ', $methods));
            self::abort(405, 'Méthode non autorisée.', $json);
        }
    }

    /**
     * Exige une requête POST avant de traiter un formulaire.
     *
     * @return void
     */
    public static function requirePost(): void
    {
        self::requireMethods(['POST']);
    }

    /**
     * Prépare les en-têtes d'une page HTML dont le rendu suit l'appel.
     *
     * @param int $status Statut HTTP de la page.
     * @return void
     */
    public static function html(int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
    }

    /**
     * Envoie un texte UTF-8 et termine la requête.
     *
     * @param string $body Contenu de la réponse.
     * @param int $status Statut HTTP.
     * @return never
     */
    public static function text(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
            echo $body;
        }
        exit;
    }

    /**
     * Envoie une réponse JSON UTF-8 et termine la requête.
     *
     * @param array<array-key, mixed> $data Données à encoder.
     * @param int $status Statut HTTP ; une erreur d'encodage impose le statut 500.
     * @return never
     */
    public static function json(array $data, int $status = 200): never
    {
        try {
            $body = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            error_log($exception->getMessage());
            $status = 500;
            $body = '{"error":"Impossible de préparer la réponse JSON."}';
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
            echo $body;
        }
        exit;
    }

    /**
     * Interrompt la requête avec une erreur texte ou un objet JSON contenant error.
     *
     * @param int $status Statut HTTP d'erreur.
     * @param string $message Message public sans détail technique sensible.
     * @param bool $json Vrai pour utiliser le format JSON.
     * @return never
     */
    public static function abort(int $status, string $message, bool $json = false): never
    {
        if ($json) {
            self::json(['error' => $message], $status);
        }
        self::text($message, $status);
    }

    /**
     * Redirige après un traitement POST pour éviter une nouvelle soumission.
     *
     * @param string $location Destination définie par l'application.
     * @return never
     */
    public static function redirect(string $location = '/'): never
    {
        header('Location: ' . $location, true, 303);
        exit;
    }
}
