<?php

namespace JDZ\Mailer\Tests;

use JDZ\Mailer\Exception\MailchimpException;
use JDZ\Mailer\Exception\SmtpException;
use JDZ\Mailer\Mailer;
use JDZ\Mailer\Tests\Support\SpyTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * 2.0: Mailer::send() builds the Email and hands it to the transport of its DSN.
 * The transport is an in-memory spy here: nothing leaves.
 */
class MailerSendTest extends TestCase
{
    private const SMTP = ['host' => 'smtp.example.com', 'user' => 'mailer', 'pass' => 'secret'];

    private SpyTransport $transport;

    /** a ready mailer whose createTransport() answers the spy and records the DSN it was given */
    private function mailer(string $debug = '', ?string $failure = null): Mailer
    {
        $this->transport = new SpyTransport($debug, $failure);

        $mailer = new class($this->transport) extends Mailer {
            public string $dsnUsed = '';

            public function __construct(private TransportInterface $spy)
            {
                parent::__construct();
            }

            protected function createTransport(string $dsn): TransportInterface
            {
                $this->dsnUsed = $dsn;

                return $this->spy;
            }
        };

        $mailer->domain = 'example.com';
        $mailer->subject = 'Your order';
        $mailer->setFrom('site@example.com', 'Site');
        $mailer->addRecipient('ann@example.com', 'Ann');
        $mailer->setContent(['isHtml' => true, 'content' => '<p>Hi</p>']);

        return $mailer;
    }

    public function testTheEmailIsHandedToTheTransportOfTheDsn(): void
    {
        $mailer = $this->mailer();
        $mailer->setSMTP(self::SMTP);

        $mailer->send();

        $this->assertSame('smtp://mailer:secret@smtp.example.com:587', $mailer->dsnUsed);
        $this->assertCount(1, $this->transport->sent);
        $email = $this->transport->sent[0];
        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('Your order', $email->getSubject());
        $this->assertSame('<p>Hi</p>', $email->getHtmlBody());
        $this->assertSame('Hi', $email->getTextBody());
        $this->assertSame([], $mailer->DebugInfos);
    }

    public function testTheTransportDebugIsAppendedToDebugInfosAfterABlankLine(): void
    {
        $mailer = $this->mailer("< 220 smtp.example.com\r\n> EHLO\r\n");
        $mailer->DebugInfos = ['earlier'];

        $mailer->send();

        $this->assertSame(['earlier', '', '< 220 smtp.example.com', '> EHLO'], $mailer->DebugInfos);
    }

    /** the README promised an SmtpException: it is thrown now (it never was) */
    public function testAnSmtpTransportFailureIsAnSmtpException(): void
    {
        $mailer = $this->mailer('', 'Connection refused');
        $mailer->setSMTP(self::SMTP);

        $this->expectException(SmtpException::class);
        $this->expectExceptionMessage('Connection refused');

        $mailer->send();
    }

    public function testANativeTransportFailureIsAnSmtpExceptionToo(): void
    {
        $mailer = $this->mailer('', 'sendmail exited with 1');

        $this->expectException(SmtpException::class);

        $mailer->send();
    }

    /** on the Mandrill SMTP route a rejected or invalid recipient is a transport error */
    public function testAMandrillFailureIsAMailchimpException(): void
    {
        $mailer = $this->mailer('', '550 Recipient rejected');
        $mailer->setMailchimp(['apiKey' => 'md-key']);

        $this->expectException(MailchimpException::class);
        $this->expectExceptionMessage('550 Recipient rejected');

        $mailer->send();
    }

    public function testAMailchimpMailGoesThroughTheBridgesSmtpTransport(): void
    {
        $mailer = $this->mailer();
        $mailer->setMailchimp(['apiKey' => 'md-key']);

        $mailer->send();

        $this->assertSame('mandrill+smtp://site%40example.com:md-key@default', $mailer->dsnUsed);
        $this->assertSame('opens,clicks', $this->transport->sent[0]->getHeaders()->get('X-MC-Track')->getBodyAsString());
    }

    // --- DKIM ---

    /** a throwaway RSA key made for the tests (openssl genrsa 2048), signing nothing real */
    private static function privateKey(): string
    {
        return (string)file_get_contents(__DIR__ . '/fixtures/dkim-test-key.pem');
    }

    public function testAValidDkimConfigSignsTheMessage(): void
    {
        $mailer = $this->mailer();
        $mailer->setSMTP(self::SMTP);
        $mailer->setDKIM(['private' => self::privateKey(), 'selector' => 'mail']);

        $mailer->send();

        $signature = $this->transport->sent[0]->getHeaders()->get('DKIM-Signature')->getBodyAsString();
        $this->assertStringContainsString('d=example.com;', $signature);
        $this->assertStringContainsString('s=mail;', $signature);
        // symfony/mime signs as the domain (i=@domain): the identity of the config is not used
        $this->assertStringContainsString('i=@example.com;', $signature);
    }

    public function testAMailchimpMailIsNotSignedHere(): void
    {
        $mailer = $this->mailer();
        $mailer->setMailchimp(['apiKey' => 'md-key']);
        $mailer->setDKIM(['private' => self::privateKey(), 'selector' => 'mail', 'domain' => 'example.com', 'identity' => 'site@example.com']);
        // setDKIM switches the type to smtp: Mailchimp is asked for explicitly
        $mailer->type = 'mailchimp';

        $mailer->send();

        $this->assertFalse($this->transport->sent[0]->getHeaders()->has('DKIM-Signature'));
    }

    public function testAnUnreadableDkimKeyIsAConfigError(): void
    {
        $mailer = $this->mailer();
        $mailer->setSMTP(self::SMTP);
        $mailer->setDKIM(['private' => 'not a key', 'selector' => 'mail']);

        $this->expectException(\JDZ\Mailer\Exception\ConfigException::class);
        $this->expectExceptionMessageMatches('/^DKIM: /');

        $mailer->send();
    }
}
