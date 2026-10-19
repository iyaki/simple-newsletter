<?php

declare(strict_types=1);

use SimpleNewsletter\Components\EndUserException;

covers(SimpleNewsletter\Components\EndUserException::class);

test('reason returns the deepest wrapped message', function (): void {
    $root = new \RuntimeException('SMTP Error: The following recipients failed: user@example.com: High load, try again later');
    $middle = new EndUserException('We could not send the confirmation email.', 0, $root);
    $outer = new EndUserException('Newsletter delivery failed for every recipient', 0, $middle);

    expect($outer->reason())->toBe('SMTP Error: The following recipients failed: user@example.com: High load, try again later');
});

test('reason falls back to the own message without a chain', function (): void {
    $exception = new EndUserException('Invalid Feed URI');

    expect($exception->reason())->toBe('Invalid Feed URI');
});

test('isTechnical only fires for wrapped PDO failures', function (): void {
    expect(new EndUserException('Invalid Feed URI')->isTechnical())->toBeFalse()
        ->and(new EndUserException('A technical error occurred.', 0, new \RuntimeException('x'))->isTechnical())->toBeFalse()
        ->and(new EndUserException('A technical error occurred.', 0, new \PDOException('lost connection'))->isTechnical())->toBeTrue();
});
