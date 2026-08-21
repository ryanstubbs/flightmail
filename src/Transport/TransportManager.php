<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Transports;
use Symfony\Component\Mailer\Transport;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use InvalidArgumentException;
use LogicException;

/**
 * Builds and holds the set of named transports from Symfony DSN strings.
 *
 * Every transport factory bundled with installed symfony/*-mailer provider
 * packages is available automatically; extra factories can be prepended for
 * fully custom schemes.
 */
final class TransportManager
{
    /**
     * @var array<string, TransportInterface>|null
     */
    private ?array $map = null;

    /**
     * @var list<TransportFactoryInterface>
     */
    private array $extraFactories = [];

    private ?EventDispatcherInterface $dispatcher = null;

    private ?LoggerInterface $logger = null;

    private ?HttpClientInterface $client = null;

    private string $defaultName = 'default';

    /**
     * @param array<string, string> $dsns transport name => Symfony DSN
     */
    public function __construct(
        private array $dsns = [],
    ) {}

    public function addDsn(string $name, string $dsn): static
    {
        $this->dsns[$name] = $dsn;
        $this->map = null;

        return $this;
    }

    public function addTransportFactory(TransportFactoryInterface $factory): static
    {
        $this->extraFactories[] = $factory;
        $this->map = null;

        return $this;
    }

    public function dispatcher(?EventDispatcherInterface $dispatcher): static
    {
        $this->dispatcher = $dispatcher;
        $this->map = null;

        return $this;
    }

    public function logger(?LoggerInterface $logger): static
    {
        $this->logger = $logger;
        $this->map = null;

        return $this;
    }

    public function client(?HttpClientInterface $client): static
    {
        $this->client = $client;
        $this->map = null;

        return $this;
    }

    public function setDefaultName(string $name): static
    {
        $this->defaultName = $name;

        return $this;
    }

    public function getDefaultName(): string
    {
        return $this->defaultName;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->map());
    }

    public function has(string $name): bool
    {
        return isset($this->map()[$name]);
    }

    public function get(string $name): TransportInterface
    {
        $map = $this->map();

        if (isset($map[$name]) === false) {
            throw new InvalidArgumentException(sprintf(
                'Unknown mail transport "%s" (configured: %s).',
                $name,
                implode(', ', array_keys($map)),
            ));
        }

        return $map[$name];
    }

    public function all(): Transports
    {
        return new Transports($this->map());
    }

    /**
     * @return array<string, TransportInterface>
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        if ($this->dsns === []) {
            throw new LogicException(
                'No mail DSNs configured. Pass ["dsns" => ["default" => "smtp://..."]] to '
                . 'MailPlugin::register(), or use "null://null" to drop mail silently in local development.'
            );
        }

        $factory = new Transport([
            ...$this->extraFactories,
            ...iterator_to_array(
                Transport::getDefaultFactories($this->dispatcher, $this->client, $this->logger),
                false,
            ),
        ]);

        $map = [];
        foreach ($this->dsns as $name => $dsn) {
            $map[$name] = $factory->fromString($dsn);
        }

        return $this->map = $map;
    }
}
