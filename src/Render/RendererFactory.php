<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Render;

use Closure;
use InvalidArgumentException;

/**
 * Creates and caches renderer instances by name. "twig" and "latte" are built
 * in; additional renderers can be registered as callables that receive the
 * plugin config array and return a RendererInterface.
 */
final class RendererFactory
{
    /**
     * @var array<string, Closure(array<string,mixed>): RendererInterface>
     */
    private array $factories = [];

    /**
     * @var array<string, RendererInterface>
     */
    private array $resolved = [];

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {
        $this->factories['twig'] = static fn(array $cfg): RendererInterface => new TwigRenderer(
            $cfg['templates']['paths'] ?? [],
            $cfg['templates']['cache'] ?? null,
            $cfg['twig']['options'] ?? [],
        );

        $this->factories['latte'] = static fn(array $cfg): RendererInterface => new LatteRenderer(
            $cfg['templates']['paths'] ?? [],
            $cfg['templates']['cache'] ?? null,
            $cfg['latte']['setup'] ?? null,
        );
    }

    /**
     * @param Closure(array<string,mixed>): RendererInterface $factory
     */
    public function add(string $name, Closure $factory): void
    {
        $this->factories[$name] = $factory;
        unset($this->resolved[$name]);
    }

    public function has(string $name): bool
    {
        return isset($this->factories[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->factories);
    }

    public function create(string $name): RendererInterface
    {
        if (isset($this->resolved[$name]) === true) {
            return $this->resolved[$name];
        }

        if (isset($this->factories[$name]) === false) {
            throw new InvalidArgumentException(sprintf(
                'Unknown mail template renderer "%s" (available: %s). Install twig/twig or latte/latte, or register a custom renderer via $plugin->addRenderer().',
                $name,
                implode(', ', array_keys($this->factories)),
            ));
        }

        return $this->resolved[$name] = ($this->factories[$name])($this->config);
    }
}
