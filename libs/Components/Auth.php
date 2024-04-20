<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

final readonly class Auth
{
    public function __construct(
        private string $secret
    )
    {}

    public function hash(string $key): string
    {
        return \password_hash($this->prepare($key), \PASSWORD_DEFAULT);
    }

    public function verify(string$key, string $token): bool
    {
        return \password_verify($this->prepare($key), $token);
    }

    private function prepare(string $key): string
    {
        $hashed_secret = \hash('sha512', $this->secret);
        return \substr($hashed_secret, 0, 256) . hash_hmac('sha256', \str_pad($key, 256, $hashed_secret, \STR_PAD_BOTH), $this->secret); \substr($hashed_secret, -256);
    }
}
