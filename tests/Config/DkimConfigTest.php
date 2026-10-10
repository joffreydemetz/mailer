<?php

namespace JDZ\Mailer\Tests\Config;

use JDZ\Mailer\Config\DkimConfig;
use JDZ\Mailer\Exception\ConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DkimConfigTest extends TestCase
{
    private const COMPLETE = [
        'domain' => 'example.com',
        'identity' => 'site@example.com',
        'private' => '/keys/dkim.pem',
        'selector' => 'mail',
    ];

    public function testAnUntouchedConfigPassesWithoutBecomingValid(): void
    {
        $config = new DkimConfig();
        $config->check();

        $this->assertFalse($config->valid);
    }

    public function testAConfigWithEveryRequiredFieldIsValidWithoutAPassphrase(): void
    {
        $config = new DkimConfig();
        $config->setProperties(self::COMPLETE);
        $config->check();

        $this->assertTrue($config->valid);
    }

    public static function missingFields(): array
    {
        return [
            'domain' => ['domain', 'Missing DKIM "domain"'],
            'identity' => ['identity', 'Missing DKIM "identity"'],
            'private' => ['private', 'Missing DKIM "private" key'],
            'selector' => ['selector', 'Missing DKIM "selector" key'],
        ];
    }

    #[DataProvider('missingFields')]
    public function testEachRequiredFieldIsChecked(string $field, string $message): void
    {
        $config = new DkimConfig();
        $config->setProperties(array_merge(self::COMPLETE, [$field => '']));

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/^' . preg_quote($message, '/') . '$/');

        $config->check();
    }
}
