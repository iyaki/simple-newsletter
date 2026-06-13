<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

final readonly class Auth
{
    public function __construct(
        #[\SensitiveParameter] private string $secret
    )
    {}

    public function hash(string $key): string
    {
        return \hash_hmac('sha256', $key, $this->secret);
    }
    // mago-ignore sensitive-parameter
    public function verify(#[\SensitiveParameter] string $key, string $token): bool
    {
        return \hash_equals($this->hash($key), $token);
    }
}
