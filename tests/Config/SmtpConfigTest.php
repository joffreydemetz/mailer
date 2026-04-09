<?php

namespace JDZ\Mailer\Tests\Config;

use PHPUnit\Framework\TestCase;
use JDZ\Mailer\Config\SmtpConfig;
use JDZ\Mailer\Exception\ConfigException;

class SmtpConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = new SmtpConfig();

        $this->assertFalse($config->use);
        $this->assertEquals(0, $config->debug);
        $this->assertEquals(587, $config->port);
        $this->assertTrue($config->auth);
        $this->assertEquals('', $config->host);
        $this->assertEquals('tls', $config->secure);
    }

    public function testSetEnablesUse(): void
    {
        $config = new SmtpConfig();
        $config->set('host', 'smtp.example.com');

        $this->assertTrue($config->use);
    }

    public function testCheckValidConfig(): void
    {
        $config = new SmtpConfig();
        $config->setProperties([
            'host' => 'smtp.example.com',
            'user' => 'user',
            'pass' => 'pass',
        ]);

        $config->check();

        $this->assertTrue($config->valid);
    }

    public function testCheckSkipsWhenNotUsed(): void
    {
        $config = new SmtpConfig();
        $config->check();

        $this->assertFalse($config->valid);
    }

    public function testCheckThrowsOnMissingHost(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing SMTP host');

        $config = new SmtpConfig();
        $config->set('host', '');
        $config->check();
    }

    public function testCheckThrowsOnMissingAuthCredentials(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Username and password required');

        $config = new SmtpConfig();
        $config->setProperties([
            'host' => 'smtp.example.com',
            'auth' => true,
            'user' => '',
            'pass' => '',
        ]);

        $config->check();
    }

    public function testCheckPassesWithAuthDisabled(): void
    {
        $config = new SmtpConfig();
        $config->setProperties([
            'host' => 'smtp.example.com',
            'auth' => false,
        ]);

        $config->check();

        $this->assertTrue($config->valid);
    }

    public function testSetInvalidPropertyThrows(): void
    {
        $this->expectException(ConfigException::class);

        $config = new SmtpConfig();
        $config->set('nonexistent', 'value');
    }
}
