<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\Authentication;

use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\Authentication\Contracts\AuthenticatorInterface;
use Shaykhnazar\HikvisionIsapi\Authentication\DigestAuthenticator;

class DigestAuthenticatorTest extends TestCase
{
    public function test_it_implements_the_authenticator_contract(): void
    {
        $this->assertInstanceOf(AuthenticatorInterface::class, new DigestAuthenticator);
    }

    public function test_it_builds_guzzle_digest_auth_options(): void
    {
        $options = (new DigestAuthenticator)->buildAuthOptions('admin', 's3cr3t');

        $this->assertSame(['auth' => ['admin', 's3cr3t', 'digest']], $options);
    }

    public function test_credentials_containing_special_characters_are_passed_through_untouched(): void
    {
        $options = (new DigestAuthenticator)->buildAuthOptions('admin@site', 'p@ss:w/ord#1');

        $this->assertSame(['admin@site', 'p@ss:w/ord#1', 'digest'], $options['auth']);
    }
}
