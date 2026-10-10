<?php

namespace JDZ\Mailer\Tests;

use PHPUnit\Framework\TestCase;
use JDZ\Mailer\Attachment;

class AttachmentTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $attachment = new Attachment('/path/to/file.pdf');

        $this->assertEquals('/path/to/file.pdf', $attachment->path);
        $this->assertEquals('', $attachment->name);
        $this->assertEquals('base64', $attachment->encoding);
        $this->assertEquals('application/octet-stream', $attachment->type);
        $this->assertEquals('attachment', $attachment->disposition);
    }
}
