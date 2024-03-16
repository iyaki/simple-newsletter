<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use Laminas\Feed\Reader\Reader;

if (! \in_array(\strtoupper($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD']), true)) {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

$feedUrl = $_GET['uri'] ?? null;

if (! $feedUrl) {
    header('Location: /');
    exit;
}

$feed = Reader::import($feedUrl);

?>

<p><?= $feed->getTitle() ?></p>
<p><?= $feed->getDescription() ?></p>
<p><?= $feed->getDateModified() ?></p>
<p><?= $feed->getLink() ?></p>
<p><?= $feed->getAuthor() ?></p>
<?php $item = $feed->current() ?>
<p><?= $item->getTitle() ?></p>
<p><?= $item->getLink() ?></p>
<p><?= $item->getDescription() ?></p>

<form method="POST" action="/subscribe/">
    <label>
        Email
        <input type="email" name="email" required>
        <button type="submit">Subscribe</button>
    </label>
    <input type="hidden" name="uri" value="<?= $feedUrl ?>" />
</form>
