<?php

namespace JDZ\Mailer\Tests\Sender;

use JDZ\Mailer\Exception\Exception;
use JDZ\Mailer\Mailer;
use JDZ\Mailer\Sender\NativeMailSender;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * NativeMailSender through a subclass that records what would go to mail().
 */
class NativeMailSenderTest extends TestCase
{
    private string $file = '';

    protected function tearDown(): void
    {
        if ('' !== $this->file && is_file($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * @param bool|\Throwable $outcome what deliver() answers in place of mail()
     */
    private function sender(bool|\Throwable $outcome = true): NativeMailSender
    {
        return new class($outcome) extends NativeMailSender {
            public array $sent = [];

            public function __construct(private bool|\Throwable $outcome) {}

            protected function deliver(string $to, string $subject, string $body, array $headers): bool
            {
                $this->sent = compact('to', 'subject', 'body', 'headers');

                if ($this->outcome instanceof \Throwable) {
                    throw $this->outcome;
                }

                return $this->outcome;
            }
        };
    }

    private function mailer(string $subject = 'Your order', array $content = ['Body' => 'Hello', 'isHtml' => false]): Mailer
    {
        $mailer = new Mailer();
        $mailer->domain = 'example.com';
        $mailer->timestamp = 'Sat, 10 Oct 2026 10:00:00 +0200';
        $mailer->subject = $subject;
        $mailer->setFrom('site@example.com', 'Site');
        $mailer->addRecipient('ann@example.com', 'Ann');
        $mailer->setContent($content);

        return $mailer;
    }

    private function attach(Mailer $mailer, string $data, string $name, string $type, string $disposition): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'jdz-mailer-');
        file_put_contents($this->file, $data);
        $mailer->addAttachment($this->file, $name, '', $type, $disposition);
    }

    private function caught(callable $call): \Throwable
    {
        try {
            $call();
        } catch (\Throwable $e) {
            return $e;
        }

        $this->fail('Nothing was thrown');
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

    public function testAnEmptyMailerSubjectFallsBackToTheDefaultOne(): void
    {
        $sender = $this->sender();
        $sender->setMailer($this->mailer(''))->send();

        $this->assertSame('Mail Subject', $sender->sent['subject']);
    }

    public function testRecipientsAreJoinedIntoTheToArgument(): void
    {
        $mailer = $this->mailer();
        $mailer->addRecipient('bob@example.com');

        $sender = $this->sender();
        $sender->setMailer($mailer)->send();

        $this->assertSame('Ann <ann@example.com>, bob@example.com', $sender->sent['to']);
    }

    public function testHeadersOfAPlainTextMail(): void
    {
        $sender = $this->sender();
        $sender->setMailer($this->mailer())->send();

        $headers = $sender->sent['headers'];
        // deliberately not pinned: Content-Transfer-Encoding is always "quoted-printable" while the
        // body is never encoded (and a multipart entity may only be 7bit/8bit/binary), and
        // X-Priority is always 1 (highest) whatever Mailer::$important says
        unset($headers['Content-Transfer-Encoding'], $headers['X-Priority']);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}@example\.com$/', $sender->messageId);
        $this->assertSame([
            'Date' => 'Sat, 10 Oct 2026 10:00:00 +0200',
            'Message-ID' => '<' . $sender->messageId . '>',
            'From' => 'Site <site@example.com>',
            'X-Sender' => 'Site <site@example.com>',
            'X-Mailer' => 'PHP/' . \PHP_VERSION,
            'Return-Path' => 'site@example.com',
            'MIME-Version' => '1.0',
            'Content-type' => 'text/plain; charset=utf-8',
        ], $headers);
    }

    public function testReplyToCcAndBccHeadersJoinEveryAddress(): void
    {
        $mailer = $this->mailer();
        $mailer->addReplyTo('help@example.com', 'Help');
        $mailer->addReplyTo('sales@example.com');
        $mailer->addCc('cy@example.com', 'Cy');
        $mailer->addCc('dee@example.com', 'Dee');
        $mailer->addBcc('audit@example.com');
        $mailer->addBcc('log@example.com', 'Log');

        $sender = $this->sender();
        $sender->setMailer($mailer)->send();

        $this->assertSame([
            'Reply-To' => 'Help <help@example.com>, sales@example.com',
            'Cc' => 'Cy <cy@example.com>, Dee <dee@example.com>',
            'Bcc' => 'audit@example.com, Log <log@example.com>',
        ], array_intersect_key($sender->sent['headers'], array_flip(['Reply-To', 'Cc', 'Bcc'])));
    }

    public static function contentTypes(): array
    {
        return [
            'plain text' => [['Body' => 'Hello', 'isHtml' => false], 'utf-8', false, 'text/plain; charset=utf-8'],
            'plain text, mailer charset' => [['Body' => 'Hello', 'isHtml' => false], 'iso-8859-1', false, 'text/plain; charset=iso-8859-1'],
            'html' => [['Body' => '<p>Hello</p>', 'isHtml' => true], 'utf-8', false, 'text/html; charset=utf-8'],
            'html with a text alternative' => [['Body' => '<p>Hello</p>', 'AltBody' => 'Hello', 'isHtml' => true], 'utf-8', false, 'multipart/alternative; boundary="{boundary}"'],
            'with an attachment' => [['Body' => 'Hello', 'isHtml' => false], 'utf-8', true, 'multipart/mixed; boundary="{boundary}"'],
        ];
    }

    #[DataProvider('contentTypes')]
    public function testContentTypeHeader(array $content, string $charset, bool $withAttachment, string $expected): void
    {
        $mailer = $this->mailer('Your order', $content);
        $mailer->charset = $charset;
        if ($withAttachment) {
            $this->attach($mailer, 'notes', 'notes.txt', 'text/plain', 'attachment');
        }

        $sender = $this->sender();
        $sender->setMailer($mailer)->send();

        $this->assertSame(str_replace('{boundary}', $sender->boundary, $expected), $sender->sent['headers']['Content-type']);
    }

    public function testAPlainTextBodyIsSentAsIs(): void
    {
        $sender = $this->sender();
        $sender->setMailer($this->mailer())->send();

        $this->assertSame('Hello', $sender->sent['body']);
    }

    public function testAnHtmlBodyIsWrappedInADocumentTitledWithTheSubject(): void
    {
        $sender = $this->sender();
        $sender->setMailer($this->mailer('Your order', ['Body' => '<p>Hello</p>', 'isHtml' => true]))->send();

        $this->assertSame(implode("\r\n", [
            '<html>',
            ' <head>',
            '  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />',
            '  <title>Your order</title>',
            ' </head>',
            ' <body>',
            '<p>Hello</p>',
            ' </body>',
            '</html>',
        ]), $sender->sent['body']);
    }

    public function testATextAlternativeIsSentAsThePartBeforeTheHtmlOne(): void
    {
        $sender = $this->sender();
        $sender->setMailer($this->mailer('Your order', ['Body' => '<p>Hello</p>', 'AltBody' => 'Hello', 'isHtml' => true]))->send();

        $this->assertMatchesRegularExpression('/^PHP-alt-[0-9a-f]{32}$/', $sender->boundary);
        // the tail is not pinned: the message should end on the close delimiter "--<boundary>--"
        // (RFC 2046) but ends on a plain "--<boundary>"
        $this->assertStringStartsWith(implode("\r\n", [
            '--' . $sender->boundary,
            'Content-Type: text/plain; charset=utf-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            'Hello',
            '',
            '--' . $sender->boundary,
            'Content-Type: text/html; charset=utf-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            '<html>',
            ' <head>',
            '  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />',
            '  <title>Your order</title>',
            ' </head>',
            ' <body>',
            '<p>Hello</p>',
            ' </body>',
            '</html>',
            '',
        ]), $sender->sent['body']);
    }

    public function testAnAttachmentIsSentBase64EncodedInItsOwnPart(): void
    {
        $mailer = $this->mailer();
        // 60 bytes -> 80 base64 chars, split at 76
        $this->attach($mailer, str_repeat('A', 60), 'notes.txt', 'text/plain', 'inline');

        $sender = $this->sender();
        $sender->setMailer($mailer)->send();

        // the tail is not pinned: see testATextAlternativeIsSentAsThePartBeforeTheHtmlOne
        $this->assertStringStartsWith(implode("\r\n", [
            '',
            '--' . $sender->boundary,
            'Content-Type: text/plain; charset=utf-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            'Hello',
            '',
            '--' . $sender->boundary,
            'Content-Type: text/plain; name="notes.txt"',
            'Content-Transfer-Encoding: base64',
            'Content-Disposition: inline; filename="notes.txt"',
            '',
            str_repeat('QUFB', 19),
            'QUFB',
            '',
        ]), $sender->sent['body']);
    }

    public static function wrappedBodies(): array
    {
        // "abcd" words: 60 of them make a 299-char line, 15 of them a 74-char one
        $words = static fn (int $count): string => rtrim(str_repeat('abcd ', $count));

        return [
            'text body, 300 columns' => [
                ['Body' => $words(61), 'isHtml' => false],
                $words(60) . "\r\nabcd",
            ],
            'html body, 76 columns' => [
                ['Body' => $words(16), 'isHtml' => true],
                " <body>\r\n" . $words(15) . "\r\nabcd\r\n </body>",
            ],
            'text alternative, 300 columns' => [
                ['Body' => '<p>Hello</p>', 'AltBody' => $words(61), 'isHtml' => true],
                "8bit\r\n\r\n" . $words(60) . "\r\nabcd\r\n\r\n--",
            ],
        ];
    }

    #[DataProvider('wrappedBodies')]
    public function testLongLinesAreWrapped(array $content, string $expected): void
    {
        $sender = $this->sender();
        $sender->setMailer($this->mailer('Your order', $content))->send();

        $this->assertStringContainsString($expected, $sender->sent['body']);
    }

    public function testAFailedDeliveryThrows(): void
    {
        $sender = $this->sender(false);
        $sender->setMailer($this->mailer());

        $e = $this->caught(static fn () => $sender->send());

        $this->assertSame(Exception::class, $e::class);
        $this->assertSame('Error sending mail', $e->getMessage());
    }

    public function testAnErrorWhileDeliveringIsRethrownAsAMailerException(): void
    {
        $error = new \RuntimeException('Connection refused', 7);
        $sender = $this->sender($error);
        $sender->setMailer($this->mailer());

        $e = $this->caught(static fn () => $sender->send());

        $this->assertSame(Exception::class, $e::class);
        $this->assertSame('Connection refused', $e->getMessage());
        $this->assertSame(7, $e->getCode());
        $this->assertSame($error, $e->getPrevious());
    }
}
