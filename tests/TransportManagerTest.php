<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Tests;

use PHPUnit\Framework\TestCase;
use ryanstubbs\FlightMail\Tests\Support\CaptureTransportFactory;
use ryanstubbs\FlightMail\Transport\TransportManager;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use InvalidArgumentException;
use LogicException;

final class TransportManagerTest extends TestCase
{
    public function testBuildsNamedTransportsFromDsns(): void
    {
        $manager = new TransportManager([
            'primary' => 'null://null',
            'backup' => 'null://null',
        ]);

        self::assertSame(['primary', 'backup'], $manager->names());
        self::assertNotSame($manager->get('primary'), $manager->get('backup'));
    }

    public function testAddDsnLazily(): void
    {
        $manager = new TransportManager(['default' => 'null://null']);
        $manager->addDsn('extra', 'null://null');

        self::assertSame(['default', 'extra'], $manager->names());
    }

    public function testUnknownTransportThrows(): void
    {
        $manager = new TransportManager(['default' => 'null://null']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown mail transport "nope"');
        $manager->get('nope');
    }

    public function testEmptyDsnsThrowsWithHelpfulMessage(): void
    {
        $manager = new TransportManager();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No mail DSNs configured');
        $manager->all();
    }

    public function testCustomTransportFactoryHandlesCustomScheme(): void
    {
        $factory = new CaptureTransportFactory();
        $manager = (new TransportManager(['main' => 'capture://main']))
            ->addTransportFactory($factory);

        $transport = $manager->get('main');

        self::assertArrayHasKey('main', $factory->created);
        self::assertSame($factory->created['main'], $transport);
    }

    public function testNullDsnProducesNullTransport(): void
    {
        $manager = new TransportManager(['default' => 'null://null']);

        self::assertInstanceOf(NullTransport::class, $manager->get('default'));
    }
}
