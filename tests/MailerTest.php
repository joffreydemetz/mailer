<?php

namespace JDZ\Mailer\Tests;

use PHPUnit\Framework\TestCase;
use JDZ\Mailer\Mailer;
use JDZ\Mailer\Address;
use JDZ\Mailer\Exception\ConfigException;

class MailerTest extends TestCase
{
    public function testConstructorInitializesConfigs(): void
    {
        $mailer = new Mailer();

        $this->assertEquals('mail', $mailer->type);
        $this->assertInstanceOf(\JDZ\Mailer\Config\SmtpConfig::class, $mailer->smtp);
        $this->assertInstanceOf(\JDZ\Mailer\Config\DkimConfig::class, $mailer->dkim);
        $this->assertInstanceOf(\JDZ\Mailer\Config\MailchimpConfig::class, $mailer->mailchimp);
        $this->assertInstanceOf(\JDZ\Mailer\Config\ContentConfig::class, $mailer->content);
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

    public function testAddRecipients(): void
    {
        $mailer = new Mailer();
        $mailer->addRecipients([
            ['email' => 'a@test.com', 'name' => 'A'],
            ['email' => 'b@test.com', 'name' => 'B'],
        ]);

        $this->assertCount(2, $mailer->recipients);
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

    public function testAddCc(): void
    {
        $mailer = new Mailer();
        $mailer->addCc('cc@example.com', 'CC');

        $this->assertCount(1, $mailer->ccs);
    }

    public function testAddBcc(): void
    {
        $mailer = new Mailer();
        $mailer->addBcc('bcc@example.com', 'BCC');

        $this->assertCount(1, $mailer->bccs);
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

    public function testAddTags(): void
    {
        $mailer = new Mailer();
        $mailer->addTags(['promo', 'weekly']);

        $this->assertCount(2, $mailer->tags);
    }

    public function testSetAsImportant(): void
    {
        $mailer = new Mailer();
        $result = $mailer->setAsImportant();

        $this->assertTrue($mailer->important);
        $this->assertSame($mailer, $result);
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

    public function testCheckThrowsOnMissingSubject(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('No Subject');

        $mailer = new Mailer();
        $mailer->setFrom('test@example.com', 'Test');
        $mailer->domain = 'example.com';
        $mailer->check();
    }

    public function testCheckThrowsOnMissingFrom(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing "from"');

        $mailer = new Mailer();
        $mailer->domain = 'example.com';
        $mailer->subject = 'Test';
        $mailer->check();
    }

    public function testCheckThrowsOnMissingDomain(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing "domain"');

        $mailer = new Mailer();
        $mailer->setFrom('test@example.com', 'Test');
        $mailer->subject = 'Test';
        $mailer->check();
    }

    public function testSetViaSetMethod(): void
    {
        $mailer = new Mailer();

        $mailer->set('from', ['email' => 'test@test.com', 'name' => 'Test']);
        $this->assertEquals('test@test.com', $mailer->from->email);

        $mailer->set('recipient', ['email' => 'r@test.com', 'name' => 'R']);
        $this->assertCount(1, $mailer->recipients);

        $mailer->set('subject', 'Hello');
        $this->assertEquals('Hello', $mailer->subject);
    }

    public function testFluentInterface(): void
    {
        $mailer = new Mailer();

        $this->assertSame($mailer, $mailer->setFrom('a@b.com'));
        $this->assertSame($mailer, $mailer->addRecipient('b@c.com'));
        $this->assertSame($mailer, $mailer->addCc('c@d.com'));
        $this->assertSame($mailer, $mailer->addBcc('d@e.com'));
        $this->assertSame($mailer, $mailer->addAttachment('/file'));
        $this->assertSame($mailer, $mailer->addTag('tag'));
        $this->assertSame($mailer, $mailer->setAsImportant());
    }
}
