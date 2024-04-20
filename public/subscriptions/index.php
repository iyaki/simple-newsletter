<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Models\Sender;

/*
    Casos de uso:
        - POST (sin token): Envía email de confirmación para subscribirse a un feed (implementar rate limiting)
        - POST (con token): Confirma subscripción y redirige a GET
        - GET (sin token): Pantalla de "log-in" (token)
        - GET (con token): Muestra lista de suscripciones y permite su gestión
        - DELETE (sin token): 401
        - DELETE (con token): Elimina suscripción (Posibilidad de eliminar todas las suscripciones?)
        - Otros: 405
 */
(function (): never {
    $method = $_SERVER['REQUEST_METHOD'];
    if (!\in_array($method, ['GET', 'DELETE'])) {
        header("Location: /", true, 302);
        exit;
    }

    $email = $_GET['email'] ?? null;
    $feedUri = $_GET['uri'] ?? null;

    if (!$email || !$feedUri) {
        \header('HTTP/1.0 400 Bad Request', true, 400);
        echo 'Fields "email" and "uri" are required';
        exit;
    }

    $c = new Container();

    if ($method === 'GET') {
        \filter_var($email, \FILTER_VALIDATE_EMAIL);
        echo '<pre>'; \var_dump($_SERVER); exit;

        exit;
    }


})();
