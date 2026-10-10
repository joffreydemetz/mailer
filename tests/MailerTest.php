<?php

namespace JDZ\Mailer\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use JDZ\Mailer\Mailer;
use JDZ\Mailer\Address;
use JDZ\Mailer\Exception\ConfigException;

class MailerTest extends TestCase
{
    /**
     * A mailer whose transports can be taken away: 'mailchimp', 'phpmailer', 'mail'.
     */
    private function mailer(string ...$unavailable): Mailer
    {
        return new class($unavailable) extends Mailer {
            public function __construct(private array $unavailable)
            {
                parent::__construct();
            }

            protected function canUseMailchimp(): bool
            {
                return !in_array('mailchimp', $this->unavailable, true) && parent::canUseMailchimp();
            }

            protected function canUsePhpMailer(): bool
            {
                return !in_array('phpmailer', $this->unavailable, true) && parent::canUsePhpMailer();
            }

            protected function canUseNativeMailer(): bool
            {
                return !in_array('mail', $this->unavailable, true) && parent::canUseNativeMailer();
            }
        };
    }

    /**
     * A mailer that passes check() as a native mail.
     */
    private function ready(string ...$unavailable): Mailer
    {
        $mailer = $this->mailer(...$unavailable);
        $mailer->domain = 'example.com';
        $mailer->subject = 'Your order';
        $mailer->setFrom('site@example.com', 'Site');

        return $mailer;
    }

    public function testSetFrom(): void
    {
        $mailer = new Mailer();
        $result = $mailer->setFrom('john@example.com', 'John');

        $this->assertInstanceOf(Address::class, $mailer->from);
        $this->assertEquals('john@example.com', $mailer->from->email);
        $this->assertEquals('John', $mailer->from->name);
        $this->assertSame($mailer, $result);
    }

    public function testSetFromIgnoresEmptyEmail(): void
    {
        $mailer = new Mailer();
        $mailer->setFrom('', 'Name');

        $this->assertNull($mailer->from);
    }

    public function testAddRecipient(): void
    {
        $mailer = new Mailer();
        $mailer->addRecipient('alice@example.com', 'Alice');
        $mailer->addRecipient('bob@example.com', 'Bob');

        $this->assertCount(2, $mailer->recipients);
        $this->assertEquals('alice@example.com', $mailer->recipients[0]->email);
        $this->assertEquals('bob@example.com', $mailer->recipients[1]->email);
    }

    public function testAddReplyTo(): void
    {
        $mailer = new Mailer();
        $result = $mailer->addReplyTo('reply@example.com', 'Reply');

        $this->assertCount(1, $mailer->replyTos);
        $this->assertSame($mailer, $result);
    }

    public function testSetNoReplyClearsReplyTos(): void
    {
        $mailer = new Mailer();
        $mailer->setNoReply('noreply@example.com', 'No Reply');

        $this->assertCount(1, $mailer->replyTos);
        $this->assertEquals('noreply@example.com', $mailer->replyTos[0]->email);
    }

    public function testAddReplyToAfterNoReplyThrows(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('noReply');

        $mailer = new Mailer();
        $mailer->setNoReply('noreply@example.com');
        $mailer->addReplyTo('reply@example.com');
    }

    public function testAddAttachment(): void
    {
        $mailer = new Mailer();
        $mailer->addAttachment('/path/file.pdf', 'doc.pdf');

        $this->assertCount(1, $mailer->attachments);
        $this->assertEquals('/path/file.pdf', $mailer->attachments[0]->path);
    }

    public function testAddAttachmentIgnoresEmptyPath(): void
    {
        $mailer = new Mailer();
        $mailer->addAttachment('');

        $this->assertEmpty($mailer->attachments);
    }

    public function testAddTag(): void
    {
        $mailer = new Mailer();
        $mailer->addTag('newsletter');
        $mailer->addTag('');

        $this->assertCount(1, $mailer->tags);
        $this->assertEquals('newsletter', $mailer->tags[0]);
    }

    public function testSetProperties(): void
    {
        $mailer = new Mailer();
        $mailer->setProperties([
            'domain' => 'example.com',
            'language' => 'en',
            'charset' => 'utf-8',
            'localMode' => true,
        ]);

        $this->assertEquals('example.com', $mailer->domain);
        $this->assertEquals('en', $mailer->language);
        $this->assertTrue($mailer->localMode);
    }

    public function testSetInvalidPropertyThrows(): void
    {
        $this->expectException(ConfigException::class);

        $mailer = new Mailer();
        $mailer->set('nonexistent', 'value');
    }

    public function testSetSMTPChangesType(): void
    {
        $mailer = new Mailer();
        $mailer->setSMTP(['host' => 'smtp.test.com', 'user' => 'u', 'pass' => 'p']);

        $this->assertEquals('smtp', $mailer->type);
        $this->assertTrue($mailer->smtp->use);
    }

    public function testSetMailchimpChangesType(): void
    {
        $mailer = new Mailer();
        $mailer->setMailchimp(['apiKey' => 'test-key']);

        $this->assertEquals('mailchimp', $mailer->type);
    }

    public function testANewMailerTurnsItsContentIntoPlainTextWithTheBasicFormatter(): void
    {
        $mailer = new Mailer();
        $mailer->setContent(['content' => '<p>Fish &amp; chips</p>']);
        $mailer->content->check();

        $this->assertSame(['Fish & chips', ''], [$mailer->content->Body, $mailer->content->AltBody]);
    }

    public static function setKeys(): array
    {
        $addresses = static fn (array $list): array => array_map('strval', $list);

        return [
            'content' => ['content', ['isHtml' => true, 'content' => '<p>Hi</p>'], static fn (Mailer $m) => [$m->content->isHtml, $m->content->content], [true, '<p>Hi</p>']],
            'smtp' => ['smtp', ['host' => 'smtp.example.com'], static fn (Mailer $m) => [$m->type, $m->smtp->use, $m->smtp->host], ['smtp', true, 'smtp.example.com']],
            'dkim' => ['dkim', ['selector' => 'mail'], static fn (Mailer $m) => [$m->type, $m->dkim->use, $m->dkim->selector], ['smtp', true, 'mail']],
            'mailchimp' => ['mailchimp', ['apiKey' => 'md-key'], static fn (Mailer $m) => [$m->type, $m->mailchimp->use, $m->mailchimp->apiKey], ['mailchimp', true, 'md-key']],
            'from' => ['from', ['email' => 'site@example.com', 'name' => 'Site'], static fn (Mailer $m) => (string)$m->from, 'Site <site@example.com>'],
            'from without a name' => ['from', ['email' => 'site@example.com'], static fn (Mailer $m) => (string)$m->from, 'site@example.com'],
            'noReply' => ['noReply', ['email' => 'robot@example.com', 'name' => 'Robot'], static fn (Mailer $m) => $addresses($m->replyTos), ['Robot <robot@example.com>']],
            'recipient' => ['recipient', ['email' => 'ann@example.com', 'name' => 'Ann'], static fn (Mailer $m) => $addresses($m->recipients), ['Ann <ann@example.com>']],
            'replyTo' => ['replyTo', ['email' => 'help@example.com'], static fn (Mailer $m) => $addresses($m->replyTos), ['help@example.com']],
            'cc' => ['cc', ['email' => 'cy@example.com', 'name' => 'Cy'], static fn (Mailer $m) => $addresses($m->ccs), ['Cy <cy@example.com>']],
            'bcc' => ['bcc', ['email' => 'audit@example.com'], static fn (Mailer $m) => $addresses($m->bccs), ['audit@example.com']],
            'recipients' => ['recipients', [['email' => 'ann@example.com', 'name' => 'Ann'], ['email' => 'bob@example.com'], ['email' => '']], static fn (Mailer $m) => $addresses($m->recipients), ['Ann <ann@example.com>', 'bob@example.com']],
            'replyTos' => ['replyTos', [['email' => 'help@example.com', 'name' => 'Help'], ['email' => 'sales@example.com']], static fn (Mailer $m) => $addresses($m->replyTos), ['Help <help@example.com>', 'sales@example.com']],
            'ccs' => ['ccs', [['email' => 'cy@example.com'], ['email' => 'dee@example.com', 'name' => 'Dee']], static fn (Mailer $m) => $addresses($m->ccs), ['cy@example.com', 'Dee <dee@example.com>']],
            'bccs' => ['bccs', [['email' => 'audit@example.com'], ['email' => 'log@example.com']], static fn (Mailer $m) => $addresses($m->bccs), ['audit@example.com', 'log@example.com']],
            'attachments' => [
                'attachments',
                [['path' => '/files/a.pdf', 'name' => 'a.pdf', 'encoding' => '7bit', 'type' => 'application/pdf', 'disposition' => 'inline'], ['path' => '/files/b.bin'], ['name' => 'no path']],
                static fn (Mailer $m) => array_map(static fn ($a) => [$a->path, $a->name, $a->encoding, $a->type, $a->disposition], $m->attachments),
                [['/files/a.pdf', 'a.pdf', '7bit', 'application/pdf', 'inline'], ['/files/b.bin', '', 'base64', 'application/octet-stream', 'attachment']],
            ],
            'tags' => ['tags', ['promo', '', 'weekly'], static fn (Mailer $m) => $m->tags, ['promo', 'weekly']],
            'any other property' => ['subject', 'Hello', static fn (Mailer $m) => $m->subject, 'Hello'],
        ];
    }

    #[DataProvider('setKeys')]
    public function testSetRoutesEachKey(string $key, mixed $value, \Closure $observe, mixed $expected): void
    {
        $mailer = new Mailer();
        $mailer->set($key, $value);

        $this->assertSame($expected, $observe($mailer));
    }

    public function testCheckFillsInTheDefaultsOfANativeMail(): void
    {
        $mailer = $this->ready();
        $mailer->check();

        $this->assertSame([
            'type' => 'mail',
            'smtp host' => 'example.com',
            'dkim domain' => 'example.com',
            'replyTos' => ['Site <site@example.com>'],
        ], [
            'type' => $mailer->type,
            'smtp host' => $mailer->smtp->host,
            'dkim domain' => $mailer->dkim->domain,
            'replyTos' => array_map('strval', $mailer->replyTos),
        ]);
    }

    public function testCheckKeepsExplicitSmtpDkimAndReplyToSettings(): void
    {
        $mailer = $this->ready();
        $mailer->setSMTP(['host' => 'smtp.example.net', 'user' => 'mailer', 'pass' => 'secret']);
        $mailer->setDKIM(['domain' => 'mail.example.com', 'identity' => 'site@example.com', 'private' => 'key', 'selector' => 'mail']);
        $mailer->addReplyTo('help@example.com', 'Help');
        $mailer->check();

        $this->assertSame([
            'type' => 'smtp',
            'smtp' => ['smtp.example.net', true],
            'dkim' => ['mail.example.com', 'site@example.com', true],
            'replyTos' => ['Help <help@example.com>'],
        ], [
            'type' => $mailer->type,
            'smtp' => [$mailer->smtp->host, $mailer->smtp->valid],
            'dkim' => [$mailer->dkim->domain, $mailer->dkim->identity, $mailer->dkim->valid],
            'replyTos' => array_map('strval', $mailer->replyTos),
        ]);
    }

    public function testAMailchimpMailNeedsNeitherFromNorDomain(): void
    {
        $mailer = $this->mailer();
        $mailer->setMailchimp(['apiKey' => 'md-key']);
        $mailer->subject = 'Newsletter';
        $mailer->check();

        $this->assertSame(
            ['type' => 'mailchimp', 'valid' => true, 'smtp host' => '', 'replyTos' => []],
            ['type' => $mailer->type, 'valid' => $mailer->mailchimp->valid, 'smtp host' => $mailer->smtp->host, 'replyTos' => $mailer->replyTos]
        );
    }

    public static function fallbacks(): array
    {
        $smtp = ['host' => 'smtp.example.com', 'user' => 'mailer', 'pass' => 'secret'];

        return [
            'mailchimp without its client -> smtp' => [['mailchimp'], static function (Mailer $m) use ($smtp): void {
                $m->setSMTP($smtp);
                $m->setMailchimp(['apiKey' => 'md-key']);
            }, ['type' => 'smtp', 'mailchimp' => false, 'smtp' => true]],
            'mailchimp without its client, no smtp config -> mail' => [['mailchimp'], static function (Mailer $m): void {
                $m->setMailchimp(['apiKey' => 'md-key']);
            }, ['type' => 'mail', 'mailchimp' => false, 'smtp' => false]],
            'smtp without PHPMailer -> mail' => [['phpmailer'], static function (Mailer $m) use ($smtp): void {
                $m->setSMTP($smtp);
            }, ['type' => 'mail', 'mailchimp' => false, 'smtp' => false]],
            'smtp without a config -> mail' => [[], static function (Mailer $m): void {
                $m->type = 'smtp';
            }, ['type' => 'mail', 'mailchimp' => false, 'smtp' => false]],
        ];
    }

    #[DataProvider('fallbacks')]
    public function testFallbackDowngradesToTheNextTransport(array $unavailable, \Closure $configure, array $expected): void
    {
        $mailer = $this->ready(...$unavailable);
        $mailer->useFallback = true;
        $configure($mailer);
        $mailer->check();

        $this->assertSame($expected, ['type' => $mailer->type, 'mailchimp' => $mailer->mailchimp->use, 'smtp' => $mailer->smtp->use]);
    }

    public static function invalidConfigs(): array
    {
        $smtp = ['host' => 'smtp.example.com', 'user' => 'mailer', 'pass' => 'secret'];

        return [
            'no from' => [[], static fn (Mailer $m) => $m->from = null, 'Missing "from" Email email@domain.tld'],
            'no domain' => [[], static fn (Mailer $m) => $m->domain = '', 'Missing "domain" Sender domain (domain.tld)'],
            'no subject' => [[], static fn (Mailer $m) => $m->subject = '', 'No Subject for this email'],
            'smtp credentials missing' => [[], static fn (Mailer $m) => $m->setSMTP(['host' => 'smtp.example.com']), 'Username and password required when SMTP auth is set to true'],
            'dkim private key missing' => [[], static fn (Mailer $m) => $m->setDKIM(['selector' => 'mail']), 'Missing DKIM "private" key'],
            'dkim selector missing' => [[], static fn (Mailer $m) => $m->setDKIM(['private' => 'key']), 'Missing DKIM "selector" key'],
            'mailchimp api key missing' => [[], static fn (Mailer $m) => $m->setMailchimp(['use' => true]), 'Missing Api Key'],
            'mailchimp client missing' => [['mailchimp'], static fn (Mailer $m) => $m->setMailchimp(['apiKey' => 'md-key']), 'Mailchimp Transactional API is required to use Mailchimp features'],
            'PHPMailer missing' => [['phpmailer'], static fn (Mailer $m) => $m->setSMTP($smtp), 'PhpMailer is required to use SMTP features'],
            'mail() missing' => [['mail'], static fn (Mailer $m) => null, 'Native mail() function is required to use Mail features'],
            'mail() missing, even with fallback' => [['mail'], static fn (Mailer $m) => $m->useFallback = true, 'Native mail() function is required to use Mail features'],
            'no transport at all' => [['mailchimp', 'phpmailer', 'mail'], static fn (Mailer $m) => $m->type = 'sendmail', 'No mailer available to send the message !'],
        ];
    }

    #[DataProvider('invalidConfigs')]
    public function testCheckRejects(array $unavailable, \Closure $configure, string $message): void
    {
        $mailer = $this->ready(...$unavailable);
        $configure($mailer);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/^' . preg_quote($message, '/') . '$/');

        $mailer->check();
    }

    public function testSendChecksTheContentBeforeAnySenderIsUsed(): void
    {
        $mailer = $this->ready();
        $mailer->addRecipient('ann@example.com');
        // an unresolvable host (RFC 2606): nothing could leave even if the order ever changed
        $mailer->setSMTP(['host' => 'smtp.invalid', 'auth' => false]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/^No message content nor template was set$/');

        $mailer->send();
    }
}
