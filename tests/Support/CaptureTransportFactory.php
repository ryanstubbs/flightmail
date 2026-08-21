<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Tests\Support;

use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class CaptureTransportFactory implements TransportFactoryInterface
{
    /** @var array<string, CaptureTransport> */
    public array $created = [];

    public function create(Dsn $dsn): TransportInterface
    {
        return $this->created[$dsn->getHost()] ??= new CaptureTransport();
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'capture';
    }
}
