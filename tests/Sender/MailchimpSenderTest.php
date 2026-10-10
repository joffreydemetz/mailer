<?php

namespace JDZ\Mailer\Tests\Sender;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use JDZ\Mailer\Exception\MailchimpException;
use JDZ\Mailer\Mailer;
use JDZ\Mailer\Sender\MailchimpSender;
use PHPUnit\Framework\TestCase;

/**
 * MailchimpSender through a subclass whose deliver() records the payload instead of
 * calling the Mailchimp Transactional API: nothing leaves.
 */
class MailchimpSenderTest extends TestCase
{
    private string $file = '';

    protected function tearDown(): void
    {
        if ('' !== $this->file && is_file($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * @param mixed $result what the API client returns (statuses, or the error it caught)
     */
    private function sender(mixed $result = []): MailchimpSender
    {
        return new class($result) extends MailchimpSender {
            public array $sent = [];

            public function __construct(private mixed $result) {}

            protected function deliver(string $apiKey, array $message): mixed
            {
                $this->sent = compact('apiKey', 'message');

                if ($this->result instanceof \Throwable && !$this->result instanceof RequestException) {
                    throw $this->result;
                }

                return $this->result;
            }
        };
    }

    private function mailer(): Mailer
    {
        $mailer = new Mailer();
        $mailer->subject = 'Your order';
        $mailer->setMailchimp(['apiKey' => 'md-key']);
        $mailer->setFrom('site@example.com', 'Site');
        $mailer->addRecipient('ann@example.com', 'Ann');
        $mailer->setContent(['Body' => '<p>Hello</p>', 'AltBody' => 'Hello', 'isHtml' => true]);

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

    public function testTheMessageIsHandedToTheApi(): void
    {
        $mailer = $this->mailer();
        $mailer->addCc('cy@example.com', 'Cy');
        $mailer->addBcc('audit@example.com');
        $mailer->addReplyTo('help@example.com', 'Help');
        $mailer->addReplyTo('sales@example.com');
        $mailer->addTag('orders');
        $mailer->setAsImportant();
        $this->file = tempnam(sys_get_temp_dir(), 'jdz-mailer-');
        file_put_contents($this->file, 'notes');
        $mailer->addAttachment($this->file, 'notes.txt', '', 'text/plain');
        $mailer->addAttachment($this->file . '-missing', 'gone.txt', '', 'text/plain');

        $sender = $this->sender();
        $sender->setMailer($mailer)->send();

        $this->assertSame('md-key', $sender->sent['apiKey']);
        $this->assertSame([
            'html' => '<p>Hello</p>',
            'text' => 'Hello',
            'subject' => 'Your order',
            'from_email' => 'site@example.com',
            'from_name' => 'Site',
            'to' => [
                ['email' => 'ann@example.com', 'name' => 'Ann', 'type' => 'to'],
                ['email' => 'cy@example.com', 'name' => 'Cy', 'type' => 'cc'],
                ['email' => 'audit@example.com', 'name' => '', 'type' => 'bcc'],
            ],
            // a file that is gone is left out
            'attachments' => [
                ['type' => 'text/plain', 'name' => 'notes.txt', 'content' => base64_encode('notes')],
            ],
            'tags' => ['orders'],
            'track_opens' => true,
            'track_clicks' => true,
            'auto_text' => true,
            'auto_html' => false,
            'preserve_recipients' => true,
            'important' => true,
            // a nameless address used to give " <sales@example.com>"
            'headers' => ['Reply-To' => 'Help <help@example.com>,sales@example.com'],
        ], $sender->sent['message']);
    }

    /**
     * The API client returns the HTTP error it caught (an invalid key, a 5xx) instead
     * of throwing it: the send used to pass for a success.
     */
    public function testAnApiErrorIsRethrownAsAMailchimpException(): void
    {
        $error = new RequestException('401 Unauthorized: Invalid API key', new Request('POST', 'https://mandrillapp.com/api/1.0/messages/send'));
        $sender = $this->sender($error);
        $sender->setMailer($this->mailer());

        $e = $this->caught(static fn () => $sender->send());

        $this->assertSame(MailchimpException::class, $e::class);
        $this->assertSame('401 Unauthorized: Invalid API key', $e->getMessage());
        $this->assertSame($error, $e->getPrevious());
    }

    public function testAClientFailureIsRethrownAsAMailchimpException(): void
    {
        $error = new \RuntimeException('cURL error 6: Could not resolve host', 6);
        $sender = $this->sender($error);
        $sender->setMailer($this->mailer());

        $e = $this->caught(static fn () => $sender->send());

        $this->assertSame([MailchimpException::class, 'cURL error 6: Could not resolve host', 6, $error], [$e::class, $e->getMessage(), $e->getCode(), $e->getPrevious()]);
    }
}
