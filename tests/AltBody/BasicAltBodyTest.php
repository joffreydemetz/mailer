<?php

namespace JDZ\Mailer\Tests\AltBody;

use PHPUnit\Framework\TestCase;
use JDZ\Mailer\AltBody\BasicAltBody;

class BasicAltBodyTest extends TestCase
{
    private BasicAltBody $converter;

    protected function setUp(): void
    {
        $this->converter = new BasicAltBody();
    }

    public function testStripsHtmlTags(): void
    {
        $result = $this->converter->convert('<p>Hello <strong>World</strong></p>');

        $this->assertEquals('Hello World', $result);
    }

    public function testStripsHeadSection(): void
    {
        $html = '<html><head><title>Title</title><style>body{}</style></head><body>Content</body></html>';

        $result = $this->converter->convert($html);

        $this->assertStringNotContainsString('Title', $result);
        $this->assertStringNotContainsString('body{}', $result);
        $this->assertStringContainsString('Content', $result);
    }

    public function testStripsScriptTags(): void
    {
        $html = '<div>Text<script>alert("xss")</script>More</div>';

        $result = $this->converter->convert($html);

        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Text', $result);
        $this->assertStringContainsString('More', $result);
    }

    public function testDecodesHtmlEntities(): void
    {
        $result = $this->converter->convert('<p>Caf&eacute; &amp; bistro</p>');

        $this->assertStringContainsString('Café', $result);
        $this->assertStringContainsString('&', $result);
    }

    public function testTrimsResult(): void
    {
        $result = $this->converter->convert('   <p>  Hello  </p>   ');

        $this->assertEquals('Hello', $result);
    }

    public function testEmptyStringReturnsEmpty(): void
    {
        $this->assertEquals('', $this->converter->convert(''));
    }
}
