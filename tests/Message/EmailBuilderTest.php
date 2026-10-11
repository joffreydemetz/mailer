<?php

namespace JDZ\Mailer\Tests\Message;

use JDZ\Mailer\Exception\ConfigException;
use JDZ\Mailer\Mailer;
use JDZ\Mailer\Message\EmailBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;

/**
 * 2.0: the facade's addresses, content and attachments become a symfony/mime Email.
 */
class EmailBuilderTest extends TestCase
{
    private ?string $tmp = null;

    protected function tearDown(): void
    {
        if (null !== $this->tmp && is_file($this->tmp)) {
            unlink($this->tmp);
        }
    }

    /** a checked mailer: from, domain, subject, an html content with its text alternative */
    private function ready(array $content = ['isHtml' => true, 'content' => '<p>Fish &amp; chips</p>']): Mailer
    {
        $mailer = new Mailer();
        $mailer->domain = 'example.com';
        $mailer->subject = 'Your order';
        $mailer->setFrom('site@example.com', 'Site');
        $mailer->addRecipient('ann@example.com', 'Ann');
        $mailer->setContent($content);

        return $mailer;
    }

    private function build(Mailer $mailer): Email
    {
        $mailer->check();
        $mailer->content->check();

        return (new EmailBuilder())->build($mailer);
    }

    private function file(string $name, string $data = 'PDF'): string
    {
        $this->tmp = sys_get_temp_dir() . '/' . $name;
        file_put_contents($this->tmp, $data);

        return $this->tmp;
    }

    private static function strings(array $addresses): array
    {
        return array_map(static fn (Address $a): string => $a->toString(), $addresses);
    }

    public function testEveryAddressListIsCarriedOver(): void
    {
        $mailer = $this->ready();
        $mailer->addRecipient('bob@example.com');
        $mailer->addReplyTo('help@example.com', 'Help');
        $mailer->addCc('cy@example.com', 'Cy');
        $mailer->addBcc('audit@example.com');

        $email = $this->build($mailer);

        $this->assertSame(['"Site" <site@example.com>'], self::strings($email->getFrom()));
        $this->assertSame(['"Ann" <ann@example.com>', 'bob@example.com'], self::strings($email->getTo()));
        $this->assertSame(['"Help" <help@example.com>'], self::strings($email->getReplyTo()));
        $this->assertSame(['"Cy" <cy@example.com>'], self::strings($email->getCc()));
        $this->assertSame(['audit@example.com'], self::strings($email->getBcc()));
        $this->assertSame('Your order', $email->getSubject());
    }

    public function testCheckGivesTheSenderAsReplyToWhenNoneIsSet(): void
    {
        $email = $this->build($this->ready());

        $this->assertSame(['"Site" <site@example.com>'], self::strings($email->getReplyTo()));
    }

    public function testAnInvalidAddressIsAConfigError(): void
    {
        $mailer = $this->ready();
        $mailer->addRecipient('not an address');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/^Invalid address: /');

        $this->build($mailer);
    }

    public function testTheTimestampIsTheDateHeader(): void
    {
        $mailer = $this->ready();
        $mailer->timestamp = 'Sun, 11 Oct 2026 10:00:00 +0200';

        $this->assertSame('Sun, 11 Oct 2026 10:00:00 +0200', $this->build($mailer)->getDate()->format(\DATE_RFC2822));
    }

    public function testWithoutATimestampTheDateIsLeftToSymfony(): void
    {
        $this->assertNull($this->build($this->ready())->getDate());
    }

    public function testAnUnreadableTimestampIsAConfigError(): void
    {
        $mailer = $this->ready();
        $mailer->timestamp = 'yesterday-ish';

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid timestamp: yesterday-ish');

        $this->build($mailer);
    }

    public function testAnHtmlContentHasItsTextAlternative(): void
    {
        $email = $this->build($this->ready());

        $this->assertSame('<p>Fish &amp; chips</p>', $email->getHtmlBody());
        $this->assertSame('Fish & chips', $email->getTextBody());
        $this->assertInstanceOf(AlternativePart::class, $email->getBody());
    }

    public function testAPlainContentIsTextOnly(): void
    {
        $email = $this->build($this->ready(['isHtml' => false, 'content' => '<p>Fish &amp; chips</p>']));

        $this->assertNull($email->getHtmlBody());
        $this->assertSame('Fish & chips', $email->getTextBody());
    }

    /**
     * D25: html + text + an attachment went out as one flat multipart/mixed, so
     * mail clients showed both versions. The alternative is nested in the mixed.
     */
    public function testTheTextAlternativeIsNestedInsideTheMixedPartWithTheAttachments(): void
    {
        $mailer = $this->ready();
        $mailer->addAttachment($this->file('report.pdf'), 'report.pdf', '', 'application/pdf');

        $body = $this->build($mailer)->getBody();

        $this->assertInstanceOf(MixedPart::class, $body);
        $this->assertInstanceOf(AlternativePart::class, $body->getParts()[0]);
        $this->assertSame('multipart/mixed', $body->getMediaType() . '/' . $body->getMediaSubtype());
    }

    public function testAnAttachmentKeepsItsNameAndType(): void
    {
        $mailer = $this->ready();
        $mailer->addAttachment($this->file('report.pdf'), 'octobre.pdf', '', 'application/pdf');

        $attachment = $this->build($mailer)->getAttachments()[0];

        $this->assertSame('octobre.pdf', $attachment->getFilename());
        $this->assertSame('application/pdf', $attachment->getMediaType() . '/' . $attachment->getMediaSubtype());
        $this->assertSame('attachment', $attachment->getDisposition());
    }

    public function testANamelessAttachmentIsNamedAfterItsFile(): void
    {
        $mailer = $this->ready();
        $mailer->addAttachment($this->file('export.csv'));

        $attachment = $this->build($mailer)->getAttachments()[0];

        $this->assertSame('export.csv', $attachment->getFilename());
        $this->assertSame('application/octet-stream', $attachment->getMediaType() . '/' . $attachment->getMediaSubtype());
    }

    public function testAnInlineAttachmentIsEmbedded(): void
    {
        $mailer = $this->ready();
        $mailer->addAttachment($this->file('logo.png'), 'logo.png', '', 'image/png', 'inline');

        $attachment = $this->build($mailer)->getAttachments()[0];

        $this->assertSame('inline', $attachment->getDisposition());
    }

    public function testAnImportantMailHasTheHighestPriority(): void
    {
        $mailer = $this->ready();
        $mailer->setAsImportant();

        $this->assertSame(Email::PRIORITY_HIGHEST, $this->build($mailer)->getPriority());
        $this->assertSame(Email::PRIORITY_NORMAL, $this->build($this->ready())->getPriority());
    }

    // --- Mandrill ---

    private function mailchimp(array $options = []): Mailer
    {
        $mailer = $this->ready();
        $mailer->setMailchimp(['apiKey' => 'md-key'] + $options);
        $mailer->addTags(['admin', 'user2admin']);

        return $mailer;
    }

    public function testAMailchimpMailCarriesTheMandrillHeaders(): void
    {
        $headers = $this->build($this->mailchimp())->getHeaders();

        $this->assertSame(['admin', 'user2admin'], array_map(static fn ($h) => $h->getValue(), iterator_to_array($headers->all('x-tag'), false)));
        $this->assertSame('opens,clicks', $headers->get('X-MC-Track')->getBodyAsString());
        $this->assertSame('true', $headers->get('X-MC-AutoText')->getBodyAsString());
        $this->assertSame('false', $headers->get('X-MC-AutoHtml')->getBodyAsString());
        $this->assertSame('true', $headers->get('X-MC-PreserveRecipients')->getBodyAsString());
        $this->assertFalse($headers->has('X-MC-Important'));
    }

    public static function tracking(): array
    {
        return [
            'opens only' => [['track_opens' => true, 'track_clicks' => false], 'opens'],
            'clicks only' => [['track_opens' => false, 'track_clicks' => true], 'clicks'],
            'neither: tracking off (an absent header would mean the account default)' => [['track_opens' => false, 'track_clicks' => false], 'none'],
        ];
    }

    #[DataProvider('tracking')]
    public function testTheTrackingFlagsBecomeXMcTrack(array $options, string $expected): void
    {
        $this->assertSame($expected, $this->build($this->mailchimp($options))->getHeaders()->get('X-MC-Track')->getBodyAsString());
    }

    public function testAnImportantMailchimpMailIsFlaggedForMandrillToo(): void
    {
        $mailer = $this->mailchimp();
        $mailer->setAsImportant();

        $this->assertSame('true', $this->build($mailer)->getHeaders()->get('X-MC-Important')->getBodyAsString());
    }

    public function testAnSmtpMailCarriesNoMandrillHeaderAndNoTag(): void
    {
        $mailer = $this->ready();
        $mailer->setSMTP(['host' => 'smtp.example.com', 'user' => 'mailer', 'pass' => 'secret']);
        $mailer->addTag('admin');

        $headers = $this->build($mailer)->getHeaders();

        $this->assertSame([], iterator_to_array($headers->all('x-tag'), false));
        $this->assertFalse($headers->has('X-MC-Track'));
    }
}
