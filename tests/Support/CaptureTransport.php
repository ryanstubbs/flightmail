<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Tests\Support;

use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\RawMessage;

final class CaptureTransport implements TransportInterface
{
    public ?RawMessage $lastMessage = null;
    public int $sendCount = 0;

    private NullTransport $inner;

    public function __construct()
    {
        $this->inner = new NullTransport();
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $this->lastMessage = $message;
        $this->sendCount++;

        return $this->inner->send($message, $envelope);
    }

    public function __toString(): string
    {
        return 'capture://test';
    }
}
