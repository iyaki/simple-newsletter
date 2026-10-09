<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

final readonly class Auth
{
    public function __construct(
        #[\SensitiveParameter]
        private string $secret,
    ) {}

    public function hash(string $key): string
    {
        return \hash_hmac('sha256', $key, $this->secret);
    }

    public function verify(#[\SensitiveParameter] string $key, #[\SensitiveParameter] string $token): bool
    {
        return \hash_equals($this->hash($key), $token);
    }

    /**
     * MAC input binding the action, the feed and the per-subscription nonce,
     * so a link authorizes exactly one action on one subscription.
     */
    public function tokenKey(string $action, string $feedUri, string $email, #[\SensitiveParameter] string $nonce): string
    {
        return $action . '|' . $feedUri . '|' . $email . '|' . $nonce;
    }

    /** @throws \Random\RandomException */
    public function newNonce(): string
    {
        return \bin2hex(\random_bytes(16));
    }
}
