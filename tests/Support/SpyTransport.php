<?php

namespace JDZ\Mailer\Tests\Support;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * An in-memory transport: records the message it is handed, answers a
 * SentMessage carrying $debug, or throws a TransportException when $failure
 * is set. Nothing leaves.
 */
class SpyTransport implements TransportInterface
{
    /** @var list<RawMessage> */
    public array $sent = [];

    public function __construct(public string $debug = '', public ?string $failure = null)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if (null !== $this->failure) {
            throw new TransportException($this->failure, 550);
        }

        $this->sent[] = $message;

        $sent = new SentMessage($message, $envelope ?? Envelope::create($message));
        $sent->appendDebug($this->debug);

        return $sent;
    }

    public function __toString(): string
    {
        return 'spy://';
    }
}
