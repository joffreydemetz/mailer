<?php

namespace JDZ\Mailer\Tests\AltBody;

use JDZ\Mailer\AltBody\SoundasleepAltBody;
use PHPUnit\Framework\TestCase;

/**
 * Needs soundasleep/html2text 2.x: 1.x rejects the char_set option, so every
 * conversion threw.
 */
class SoundasleepAltBodyTest extends TestCase
{
    public function testConvertsHtmlToTextDroppingLinkTargets(): void
    {
        $html = '<h1>Your order</h1><p>Thank you, <b>Ann</b>.</p><p>See <a href="https://example.com/orders/12">your order</a>.</p>';

        $this->assertSame("Your order\n\nThank you, Ann.\n\nSee your order.", (new SoundasleepAltBody())->convert($html));
    }
}
