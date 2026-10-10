<?php

namespace JDZ\Mailer\Tests\Sender;

use JDZ\Mailer\Exception\Exception;
use JDZ\Mailer\Mailer;
use JDZ\Mailer\Sender\PhpmailerSender;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PhpmailerSender through a subclass whose postSend() (PHPMailer's transport step) records
 * instead of sending: the message is configured and built by PHPMailer, nothing leaves.
 */
class PhpmailerSenderTest extends TestCase
{
    private string $file = '';

    protected function tearDown(): void
    {
        if ('' !== $this->file && is_file($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * @param ?\Throwable $failure thrown by postSend() in place of the transport error
     */
    private function sender(?\Throwable $failure = null): PhpmailerSender
    {
        return new class($failure) extends PhpmailerSender {
            public int $posts = 0;

            public function __construct(private ?\Throwable $failure)
            {
                parent::__construct();
            }

            public function postSend()
            {
                $this->posts++;

                if (null !== $this->failure) {
                    throw $this->failure;
                }

                return true;
            }
        };
    }

    private function mailer(array $smtp = ['host' => 'smtp.example.com', 'user' => 'mailer', 'pass' => 'secret']): Mailer
    {
        $mailer = new Mailer();
        $mailer->domain = 'example.com';
        $mailer->subject = 'Your order';
        $mailer->setFrom('site@example.com', 'Site');
        $mailer->addRecipient('ann@example.com', 'Ann');
        $mailer->setContent(['content' => 'Hello']);
        $mailer->setSMTP($smtp);

        return $mailer;
    }

    /**
     * What Mailer::send() does before handing the mailer to its sender.
     */
    private function prepare(Mailer $mailer): Mailer
    {
        $mailer->check();
        $mailer->content->check();

        return $mailer;
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

    public static function smtpSettings(): array
    {
        return [
            'config defaults' => [
                ['host' => 'smtp.example.com', 'user' => 'mailer', 'pass' => 'secret'],
                ['Host' => 'smtp.example.com', 'Port' => 587, 'Username' => 'mailer', 'Password' => 'secret', 'SMTPAuth' => true, 'SMTPDebug' => 0, 'SMTPSecure' => 'tls', 'SMTPOptions' => [], 'CharSet' => 'utf-8', 'WordWrap' => 70, 'Timeout' => 300],
            ],
            'explicit values' => [
                ['host' => 'mail.example.net', 'port' => 465, 'secure' => 'ssl', 'auth' => false, 'debug' => 1],
                ['Host' => 'mail.example.net', 'Port' => 465, 'Username' => '', 'Password' => '', 'SMTPAuth' => false, 'SMTPDebug' => 1, 'SMTPSecure' => 'ssl', 'SMTPOptions' => [], 'CharSet' => 'utf-8', 'WordWrap' => 70, 'Timeout' => 300],
            ],
        ];
    }

    #[DataProvider('smtpSettings')]
    public function testAValidSmtpConfigIsCopiedToPhpmailer(array $smtp, array $expected): void
    {
        $sender = $this->sender();
        $sender->setMailer($this->prepare($this->mailer($smtp)))->send();

        $actual = [];
        foreach (array_keys($expected) as $property) {
            $actual[$property] = $sender->{$property};
        }
        $this->assertSame($expected, $actual);
    }

    public function testLocalModeAcceptsSelfSignedCertificates(): void
    {
        $mailer = $this->prepare($this->mailer());
        $mailer->localMode = true;

        $sender = $this->sender();
        $sender->setMailer($mailer)->send();

        $this->assertSame([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ], $sender->SMTPOptions);
    }

    public static function transports(): array
    {
        return [
            'smtp' => ['smtp', 'smtp'],
            'sendmail' => ['sendmail', 'sendmail'],
            'qmail' => ['qmail', 'qmail'],
            'mail' => ['mail', 'mail'],
            'anything else' => ['pigeon', 'mail'],
        ];
    }

    #[DataProvider('transports')]
    public function testTheMailerTypeSelectsThePhpmailerTransport(string $type, string $expected): void
    {
        $mailer = $this->prepare($this->mailer());
        $mailer->type = $type;

        $sender = $this->sender();
        $sender->setMailer($mailer)->send();

        $this->assertSame($expected, $sender->Mailer);
    }

    public static function dkimSettings(): array
    {
        return [
            'valid dkim config' => [
                ['domain' => 'example.com', 'identity' => 'site@example.com', 'private' => '/nonexistent/dkim.pem', 'selector' => 'mail', 'passphrase' => 'phrase'],
                ['DKIM_domain' => 'example.com', 'DKIM_identity' => 'site@example.com', 'DKIM_private' => '/nonexistent/dkim.pem', 'DKIM_selector' => 'mail', 'DKIM_passphrase' => 'phrase'],
            ],
            'no dkim config' => [
                [],
                ['DKIM_domain' => '', 'DKIM_identity' => '', 'DKIM_private' => '', 'DKIM_selector' => '', 'DKIM_passphrase' => ''],
            ],
        ];
    }

    #[DataProvider('dkimSettings')]
    public function testAValidDkimConfigIsCopiedToPhpmailer(array $dkim, array $expected): void
    {
        $mailer = $this->mailer();
        $mailer->setDKIM($dkim);

        $sender = $this->sender();
        $sender->setMailer($this->prepare($mailer))->send();

        $actual = [];
        foreach (array_keys($expected) as $property) {
            $actual[$property] = $sender->{$property};
        }
        $this->assertSame($expected, $actual);
    }

    public static function contentTypes(): array
    {
        return [
            'plain text' => [false, 'text/plain'],
            'html' => [true, 'text/html'],
        ];
    }

    #[DataProvider('contentTypes')]
    public function testTheContentFlagSetsTheContentType(bool $isHtml, string $expected): void
    {
        $mailer = $this->mailer();
        $mailer->setContent(['Body' => '<p>Hello</p>', 'isHtml' => $isHtml]);
        $mailer->check();

        $sender = $this->sender();
        $sender->setMailer($mailer)->send();

        $this->assertSame($expected, $sender->ContentType);
    }

    public function testTheMessageIsHandedToPhpmailerAndPostedOnce(): void
    {
        $mailer = $this->mailer();
        $mailer->setContent(['isHtml' => true, 'content' => '<p>Hello</p>']);
        $mailer->addRecipient('bob@example.com');
        $mailer->addReplyTo('help@example.com', 'Help');
        $mailer->addCc('cy@example.com', 'Cy');
        $mailer->addBcc('audit@example.com');
        $this->file = tempnam(sys_get_temp_dir(), 'jdz-mailer-');
        file_put_contents($this->file, 'notes');
        $mailer->addAttachment($this->file, 'notes.txt', '', 'text/plain');

        $sender = $this->sender();
        $sender->setMailer($this->prepare($mailer))->send();

        $this->assertSame([
            'Subject' => 'Your order',
            'Body' => '<p>Hello</p>',
            // PHPMailer word-wraps the text part to WordWrap (70) when it builds the message
            'AltBody' => "Hello\r\n",
            'From' => 'site@example.com',
            'FromName' => 'Site',
            'to' => [['ann@example.com', 'Ann'], ['bob@example.com', '']],
            'replyTo' => ['help@example.com' => ['help@example.com', 'Help']],
            'cc' => [['cy@example.com', 'Cy']],
            'bcc' => [['audit@example.com', '']],
            'attachments' => [[$this->file, basename($this->file), 'notes.txt', 'base64', 'text/plain', false, 'attachment', 'notes.txt']],
            'posts' => 1,
            'DebugInfos' => [],
        ], [
            'Subject' => $sender->Subject,
            'Body' => $sender->Body,
            'AltBody' => $sender->AltBody,
            'From' => $sender->From,
            'FromName' => $sender->FromName,
            'to' => $sender->getToAddresses(),
            'replyTo' => $sender->getReplyToAddresses(),
            'cc' => $sender->getCcAddresses(),
            'bcc' => $sender->getBccAddresses(),
            'attachments' => $sender->getAttachments(),
            'posts' => $sender->posts,
            'DebugInfos' => $mailer->DebugInfos,
        ]);
    }

    public static function phpmailerErrors(): array
    {
        return [
            'stop message' => [PHPMailer::STOP_MESSAGE, 'continue processing'],
            'stop continue' => [PHPMailer::STOP_CONTINUE, 'likely ok to continue processing'],
            'stop critical' => [PHPMailer::STOP_CRITICAL, 'full stop, critical error reached'],
        ];
    }

    #[DataProvider('phpmailerErrors')]
    public function testAPhpmailerErrorIsLoggedThenRethrownAsAMailerException(int $code, string $verdict): void
    {
        $error = new PHPMailerException('SMTP connect() failed.', $code);
        $mailer = $this->prepare($this->mailer());
        $sender = $this->sender($error);
        $sender->setMailer($mailer);

        $e = $this->caught(static fn () => $sender->send());

        $this->assertSame(Exception::class, $e::class);
        $this->assertSame('SMTP connect() failed.', $e->getMessage());
        $this->assertSame($code, $e->getCode());
        $this->assertSame($error, $e->getPrevious());
        $this->assertSame(['PHPmailer error', 'CODE    : ' . $code, 'MESSAGE : SMTP connect() failed.', $verdict], $mailer->DebugInfos);
    }

    public function testAnyOtherErrorIsLoggedThenRethrownAsAMailerException(): void
    {
        $error = new \RuntimeException('Disk full', 5);
        $mailer = $this->prepare($this->mailer());
        $sender = $this->sender($error);
        $sender->setMailer($mailer);

        $e = $this->caught(static fn () => $sender->send());

        $this->assertSame(Exception::class, $e::class);
        $this->assertSame('Disk full', $e->getMessage());
        $this->assertSame(5, $e->getCode());
        $this->assertSame($error, $e->getPrevious());
        $this->assertSame(['PhpmailerSender error', 'CODE    : 5', 'MESSAGE : Disk full'], $mailer->DebugInfos);
    }

    public function testDebugInfosAreAppendedAfterABlankLine(): void
    {
        $mailer = $this->prepare($this->mailer());
        $mailer->DebugInfos = ['earlier run'];
        $sender = $this->sender(new \RuntimeException('Disk full', 5));
        $sender->setMailer($mailer);

        $this->caught(static fn () => $sender->send());

        $this->assertSame(['earlier run', '', 'PhpmailerSender error', 'CODE    : 5', 'MESSAGE : Disk full'], $mailer->DebugInfos);
    }

    /**
     * Addresses and attachments are handed to PHPMailer inside the error handling:
     * a PHPMailer error there used to escape raw and unlogged.
     */
    public function testAnAttachmentErrorIsLoggedThenRethrownAsAMailerException(): void
    {
        $mailer = $this->mailer();
        $this->file = tempnam(sys_get_temp_dir(), 'jdz-mailer-');
        file_put_contents($this->file, 'notes');
        $mailer->addAttachment($this->file, 'notes.txt');
        $this->prepare($mailer);
        unlink($this->file);

        $sender = $this->sender();
        $sender->setMailer($mailer);
        $e = $this->caught(static fn () => $sender->send());

        $this->assertSame(Exception::class, $e::class);
        $this->assertInstanceOf(PHPMailerException::class, $e->getPrevious());
        $this->assertSame(['PHPmailer error', 'CODE    : ' . $e->getCode()], array_slice($mailer->DebugInfos, 0, 2));
        $this->assertSame(0, $sender->posts, 'nothing was posted');
    }
}
