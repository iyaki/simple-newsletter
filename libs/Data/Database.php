<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

/** @internal */
final class Database extends \PDO
{
    public function __construct(array $config)
    {
        parent::__construct(...$config);
    }
}
