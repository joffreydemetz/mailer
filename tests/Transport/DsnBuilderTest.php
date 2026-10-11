<?php

namespace JDZ\Mailer\Tests\Transport;

use JDZ\Mailer\Mailer;
use JDZ\Mailer\Transport\DsnBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * 2.0: the facade's type and configs become a symfony/mailer DSN.
 */
class DsnBuilderTest extends TestCase
{
    private const SMTP = ['host' => 'smtp.example.com', 'user' => 'mailer', 'pass' => 's@cret'];

    /** a mailer that passes check() as a native mail */
    private function ready(): Mailer
    {
        $mailer = new Mailer();
        $mailer->domain = 'example.com';
        $mailer->subject = 'Your order';
        $mailer->setFrom('site@example.com', 'Site');

        return $mailer;
    }

    private function dsn(Mailer $mailer): string
    {
        $mailer->check();

        return (new DsnBuilder())->build($mailer);
    }

    public static function dsns(): array
    {
        return [
            'native mail' => [static fn (Mailer $m) => null, 'native://default'],
            'smtp with credentials, the password url-encoded' => [static fn (Mailer $m) => $m->setSMTP(self::SMTP), 'smtp://mailer:s%40cret@smtp.example.com:587'],
            'smtp without auth' => [static fn (Mailer $m) => $m->setSMTP(['host' => 'smtp.example.com', 'auth' => false, 'port' => 25]), 'smtp://smtp.example.com:25'],
            'smtps when secure is ssl' => [static fn (Mailer $m) => $m->setSMTP(self::SMTP + ['secure' => 'ssl', 'port' => 465]), 'smtps://mailer:s%40cret@smtp.example.com:465'],
            'localMode skips the peer verification' => [static function (Mailer $m): void {
                $m->localMode = true;
                $m->setSMTP(self::SMTP);
            }, 'smtp://mailer:s%40cret@smtp.example.com:587?verify_peer=0'],
            'mailchimp: the bridge\'s smtp transport, the from address as username' => [static fn (Mailer $m) => $m->setMailchimp(['apiKey' => 'md-key']), 'mandrill+smtp://site%40example.com:md-key@default'],
            'mailchimp with its own username' => [static fn (Mailer $m) => $m->setMailchimp(['apiKey' => 'md-key', 'user' => 'Citadelles & Mazenod']), 'mandrill+smtp://Citadelles%20%26%20Mazenod:md-key@default'],
            'sendmail' => [static fn (Mailer $m) => $m->type = 'sendmail', 'sendmail://default'],
            'qmail' => [static fn (Mailer $m) => $m->type = 'qmail', 'sendmail://default?command=%2Fvar%2Fqmail%2Fbin%2Fsendmail%20-t'],
            'a dsn set on the mailer wins' => [static function (Mailer $m): void {
                $m->setSMTP(self::SMTP);
                $m->dsn = 'smtp://other.example.com';
            }, 'smtp://other.example.com'],
            'fallback: mailchimp, then smtp, then mail' => [static function (Mailer $m): void {
                $m->useFallback = true;
                $m->setSMTP(self::SMTP);
                $m->setMailchimp(['apiKey' => 'md-key']);
            }, 'failover(mandrill+smtp://site%40example.com:md-key@default smtp://mailer:s%40cret@smtp.example.com:587 native://default)'],
            'fallback: smtp, then mail' => [static function (Mailer $m): void {
                $m->useFallback = true;
                $m->setSMTP(self::SMTP);
            }, 'failover(smtp://mailer:s%40cret@smtp.example.com:587 native://default)'],
            'fallback with nothing to fall back to is no failover' => [static fn (Mailer $m) => $m->useFallback = true, 'native://default'],
        ];
    }

    #[DataProvider('dsns')]
    public function testTheDsnOfAMailer(\Closure $configure, string $expected): void
    {
        $mailer = $this->ready();
        $configure($mailer);

        $this->assertSame($expected, $this->dsn($mailer));
    }

    /** every DSN the builder produces is one symfony/mailer (+ the Mailchimp bridge) accepts */
    #[DataProvider('dsns')]
    public function testSymfonyBuildsATransportFromIt(\Closure $configure, string $expected): void
    {
        $this->assertInstanceOf(TransportInterface::class, Transport::fromDsn($expected));
    }
}
