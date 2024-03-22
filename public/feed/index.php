<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use Laminas\Feed\Reader\Reader;

if (! \in_array(\strtoupper($_SERVER['REQUEST_METHOD']), ['GET', 'HEAD'], true)) {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

$feedUrl = $_GET['uri'] ?? null;

if (! $feedUrl) {
    header('Location: /');
    exit;
}

$c = new Container();

$feedsModel = $c->feeds();

$feed = $feedsModel->retrieve($feedUrl);

?>

<p><a href="<?= $feed->link ?>" rel="nofollow"><?= $feed->title ?></a></p>

<form method="POST" action="/subscriptions/">
    <label>
        Email
        <input type="email" name="email" required>
        <button type="submit">Subscribe</button>
    </label>
    <input type="hidden" name="uri" value="<?= $feedUrl ?>" />
</form>
