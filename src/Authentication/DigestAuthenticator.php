<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Authentication;

use Shaykhnazar\HikvisionIsapi\Authentication\Contracts\AuthenticatorInterface;

class DigestAuthenticator implements AuthenticatorInterface
{
    public function buildAuthOptions(string $username, string $password): array
    {
        return [
            'auth' => [$username, $password, 'digest'],
        ];
    }
}
