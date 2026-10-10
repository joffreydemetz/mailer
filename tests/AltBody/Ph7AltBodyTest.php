<?php

namespace JDZ\Mailer\Tests\AltBody;

use JDZ\Mailer\AltBody\Ph7AltBody;
use PHPUnit\Framework\TestCase;

class Ph7AltBodyTest extends TestCase
{
    public function testConvertsHtmlToTextKeepingLinkTargets(): void
    {
        $html = '<h1>Title</h1><p>Hello <a href="https://example.com">link</a> &amp; <b>bold</b></p>';

        $this->assertSame("Title\n\nHello link (https://example.com) & bold", (new Ph7AltBody())->convert($html));
    }
}
