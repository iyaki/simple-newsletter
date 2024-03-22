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
    $email = $_POST['email'] ?? null;
    $feedUri = $_POST['uri'] ?? null;
    $token = $_GET['token'] ?? null;
    $method = $_SERVER['REQUEST_METHOD'];
    $c = new Container();

    if ($method === 'POST') {
        if ($email === null || $feedUri === null) {
            \header('HTTP/1.0 400 Bad Request');
        }

        if ($token === null) {
            echo '<pre>'; \var_dump('pasó'); exit;
            $c = new Container();
            $feed = $c->feeds()->retrieve($feedUri);

            $sender = new Sender();
            $sender->subscriptionConfirmation($email, $feed->title, $feed->link);
        } else {
            // Confirma subscripción y redirige a GET
        }
    } elseif ($method === 'GET') {
        if ($token === null) {
            // Pantalla de "log-in" (token)
        } else {
            // Muestra lista de suscripciones y permite su gestión
        }
    } elseif ($method === 'DELETE') {
        if ($token === null) {
            // 401
        } else {
            // Elimina suscripción (Posibilidad de eliminar todas las suscripciones?)
        }
    } else {
        \header('HTTP/1.0 405 Method Not Allowed');
    }
})();
