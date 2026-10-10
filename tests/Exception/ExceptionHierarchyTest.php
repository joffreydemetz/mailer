<?php

namespace JDZ\Mailer\Tests\Exception;

use JDZ\Mailer\Exception\ConfigException;
use JDZ\Mailer\Exception\Exception;
use JDZ\Mailer\Exception\MailchimpException;
use JDZ\Mailer\Exception\SmtpException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The catch contract of the README: sender errors share the mailer Exception,
 * configuration errors are logic errors outside it.
 */
class ExceptionHierarchyTest extends TestCase
{
    public static function exceptions(): array
    {
        return [
            'ConfigException' => [ConfigException::class, [\LogicException::class, \Exception::class]],
            'Exception' => [Exception::class, [\Exception::class]],
            'MailchimpException' => [MailchimpException::class, [Exception::class, \Exception::class]],
            'SmtpException' => [SmtpException::class, [Exception::class, \Exception::class]],
        ];
    }

    #[DataProvider('exceptions')]
    public function testParents(string $class, array $parents): void
    {
        $this->assertSame($parents, array_values(class_parents($class)));
    }
}
