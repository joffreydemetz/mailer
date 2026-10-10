<?php

namespace JDZ\Mailer\Tests;

use PHPUnit\Framework\TestCase;
use JDZ\Mailer\Address;

class AddressTest extends TestCase
{
    public function testToStringWithName(): void
    {
        $address = new Address('john@example.com', 'John Doe');

        $this->assertEquals('John Doe <john@example.com>', (string)$address);
    }

    public function testToStringWithoutName(): void
    {
        $address = new Address('john@example.com');

        $this->assertEquals('john@example.com', (string)$address);
    }

    public function testCleansNewlines(): void
    {
        $address = new Address("john@example.com\r\n", "John\nDoe");

        $this->assertEquals('john@example.com', $address->email);
        $this->assertEquals('JohnDoe', $address->name);
    }

    public function testCleansEncodedNewlines(): void
    {
        $address = new Address('john%0A@example.com', 'John%0DDoe');

        $this->assertEquals('john@example.com', $address->email);
        $this->assertEquals('JohnDoe', $address->name);
    }

    public function testTrimsWhitespace(): void
    {
        $address = new Address('  john@example.com  ', '  John  ');

        $this->assertEquals('john@example.com', $address->email);
        $this->assertEquals('John', $address->name);
    }
}
