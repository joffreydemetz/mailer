<?php

namespace JDZ\Mailer\Tests\Config;

use PHPUnit\Framework\TestCase;
use JDZ\Mailer\Config\ContentConfig;
use JDZ\Mailer\AltBody\BasicAltBody;
use JDZ\Mailer\Exception\ConfigException;

class ContentConfigTest extends TestCase
{
    private function createConfig(array $overrides = []): ContentConfig
    {
        $config = new ContentConfig();
        $config->altBodyFormatter = new BasicAltBody();
        $config->setProperties(array_merge([
            'isHtml' => true,
            'content' => '<p>Hello</p>',
            'template' => '{{BODY}}',
        ], $overrides));
        return $config;
    }

    public function testCheckValidHtmlContent(): void
    {
        $config = $this->createConfig();
        $config->check();

        $this->assertTrue($config->valid);
        $this->assertStringContainsString('Hello', $config->Body);
    }

    public function testCheckGeneratesAltBody(): void
    {
        $config = $this->createConfig(['content' => '<h1>Title</h1><p>Body text</p>']);
        $config->check();

        $this->assertStringContainsString('Title', $config->AltBody);
        $this->assertStringContainsString('Body text', $config->AltBody);
        $this->assertStringNotContainsString('<h1>', $config->AltBody);
    }

    public function testCheckThrowsOnEmptyContent(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('No message content');

        $config = $this->createConfig(['content' => '']);
        $config->check();
    }

    public function testCheckThrowsOnTemplateMissingPlaceholder(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('{{BODY}} placeholder');

        $config = $this->createConfig(['template' => '<div>No placeholder</div>']);
        $config->check();
    }

    public function testTemplateOnlyMode(): void
    {
        $config = new ContentConfig();
        $config->altBodyFormatter = new BasicAltBody();
        $config->setProperties([
            'templateOnly' => true,
            'template' => '<html><body>Full template</body></html>',
        ]);

        $config->check();

        $this->assertTrue($config->valid);
        $this->assertEquals('<html><body>Full template</body></html>', $config->Body);
    }

    public function testTemplateOnlyThrowsOnEmptyTemplate(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Template is not set');

        $config = new ContentConfig();
        $config->altBodyFormatter = new BasicAltBody();
        $config->setProperties([
            'templateOnly' => true,
            'template' => '',
        ]);

        $config->check();
    }

    public function testContentWrappedInTemplate(): void
    {
        $config = $this->createConfig([
            'template' => '<html>{{BODY}}</html>',
            'content' => '<p>Content</p>',
        ]);

        $config->check();

        $this->assertEquals('<html><p>Content</p></html>', $config->Body);
    }

    public function testMaxWidthWrapsTemplate(): void
    {
        $config = $this->createConfig([
            'maxMailContentWidth' => 600,
        ]);

        $config->check();

        $this->assertStringContainsString('max-width:600px', $config->Body);
    }

    public function testPlainTextContent(): void
    {
        $config = new ContentConfig();
        $config->altBodyFormatter = new BasicAltBody();
        $config->setProperties([
            'isHtml' => false,
            'content' => '<p>Hello World</p>',
        ]);

        $config->check();

        $this->assertStringNotContainsString('<p>', $config->Body);
        $this->assertStringContainsString('Hello World', $config->Body);
        $this->assertEquals('', $config->AltBody);
    }

    public function testReplacements(): void
    {
        $config = $this->createConfig([
            'content' => '<p>Hello {{NAME}}</p>',
            'replacements' => ['{{NAME}}' => 'World'],
        ]);

        $config->check();

        $this->assertStringContainsString('Hello World', $config->Body);
        $this->assertStringNotContainsString('{{NAME}}', $config->Body);
    }
}
