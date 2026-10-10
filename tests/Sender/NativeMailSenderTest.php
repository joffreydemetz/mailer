<?php

namespace JDZ\Mailer\Tests\Sender;

use JDZ\Mailer\Mailer;
use JDZ\Mailer\Sender\NativeMailSender;
use PHPUnit\Framework\TestCase;

/**
 * NativeMailSender through a subclass that records what would go to mail().
 */
class NativeMailSenderTest extends TestCase
{
    private function sender(): NativeMailSender
    {
        return new class extends NativeMailSender {
            public array $sent = [];

            protected function deliver(string $to, string $subject, string $body, array $headers): bool
            {
                $this->sent = compact('to', 'subject', 'body', 'headers');

                return true;
            }
        };
    }

    private function mailer(string $subject): Mailer
    {
        $mailer = new Mailer();
        $mailer->domain = 'example.com';
        $mailer->timestamp = 'Sat, 10 Oct 2026 10:00:00 +0200';
        $mailer->subject = $subject;
        $mailer->setFrom('site@example.com', 'Site');
        $mailer->addRecipient('ann@example.com', 'Ann');
        $mailer->setContent(['Body' => 'Hello', 'isHtml' => false]);

        return $mailer;
    }

    public function testTheMailerSubjectIsSent(): void
    {
        $sender = $this->sender();
        $sender->setMailer($this->mailer('Your order'))->send();

        $this->assertSame('Your order', $sender->sent['subject']);
        $this->assertSame('Ann <ann@example.com>', $sender->sent['to']);
        $this->assertSame('Site <site@example.com>', $sender->sent['headers']['From']);
    }

    public function testANonAsciiSubjectIsMimeEncoded(): void
    {
        $sender = $this->sender();
        $sender->setMailer($this->mailer('Votre commande à été expédiée'))->send();

        // RFC 2047: the header itself is ASCII, the accented words are encoded words
        $this->assertMatchesRegularExpression('/^[\x20-\x7E\r\n\t]+$/', $sender->sent['subject']);
        $this->assertStringContainsString('=?UTF-8?B?', $sender->sent['subject']);
        $this->assertSame('Votre commande à été expédiée', mb_decode_mimeheader($sender->sent['subject']));
    }
}
