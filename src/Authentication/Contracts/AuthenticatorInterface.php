<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Authentication\Contracts;

interface AuthenticatorInterface
{
    public function buildAuthOptions(string $username, #[\SensitiveParameter] string $password): array;
}
