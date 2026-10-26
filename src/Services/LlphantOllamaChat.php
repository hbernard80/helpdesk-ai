<?php
declare(strict_types=1);

namespace App\Services;

use LLPhant\Chat\OllamaChat;
use Psr\Http\Message\ResponseInterface;

/** Conserve les métadonnées que generateChat() de LLPhant ne retourne pas. */
final class LlphantOllamaChat extends OllamaChat
{
    public array $response = [];

    protected function sendRequest(string $method, string $path, array $json): ResponseInterface
    {
        $this->response = [];
        $response = parent::sendRequest($method, $path, $json);
        $this->response = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $response->getBody()->rewind();

        return $response;
    }
}
