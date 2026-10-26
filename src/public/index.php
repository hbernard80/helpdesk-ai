<?php
declare(strict_types=1);

// TODO : Authentification

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Csrf;
use App\Services\Database;
use App\Services\Http;
use App\Services\TicketRepository;
use App\Services\TicketValidator;

Http::requireMethods(['GET', 'HEAD', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValidToken();
}
$csrfToken = Csrf::token();

$errors = [];
$title = '';
$description = '';
$tickets = [];
$status = 200;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validation = TicketValidator::creation($_POST);
    $title = $validation['title'];
    $description = $validation['description'];
    $errors = $validation['errors'];
    if ($errors !== []) {
        $status = 422;
    }
}

if ($errors === []) {
    try {
        $repository = new TicketRepository(Database::getConnection());

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $repository->create($title, $description);
            Http::redirect();
        }

        $tickets = $repository->findAll();
    } catch (Throwable $exception) {
        $creating = $_SERVER['REQUEST_METHOD'] === 'POST';
        error_log(($creating ? 'Création du ticket : ' : 'Lecture des tickets : ') . $exception->getMessage());
        $status = 500;
        $errors['server'] = $creating
            ? "Impossible d'enregistrer le ticket pour le moment. Votre saisie est conservée."
            : 'Impossible de charger les tickets pour le moment. Réessayez plus tard.';
    }
}

Http::html($status);
require __DIR__ . '/../templates/tickets/page.php';
