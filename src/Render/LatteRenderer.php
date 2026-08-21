<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Render;

use Latte\Engine;
use Closure;
use RuntimeException;

final class LatteRenderer implements RendererInterface
{
    private ?Engine $engine = null;

    /**
     * @param list<string>                 $paths     template search paths, searched in order
     * @param string|null                  $cachePath compiled-template cache directory
     * @param Closure(Engine): void|null   $setup     optional callback to tweak the Latte engine
     */
    public function __construct(
        private readonly array $paths,
        private readonly ?string $cachePath = null,
        private readonly ?Closure $setup = null,
    ) {}

    public static function isAvailable(): bool
    {
        return class_exists(Engine::class);
    }

    public static function notInstalled(): RuntimeException
    {
        return new RuntimeException(
            'Latte is not installed. Run "composer require latte/latte" or choose another renderer.'
        );
    }

    public function engine(): Engine
    {
        if ($this->engine === null) {
            if (self::isAvailable() === false) {
                throw self::notInstalled();
            }

            $engine = new Engine();
            $engine->setTempDirectory($this->cachePath ?? sys_get_temp_dir() . '/flightmail-latte');
            $engine->setLoader(new MultiPathLoader($this->paths));

            if ($this->setup !== null) {
                ($this->setup)($engine);
            }

            $this->engine = $engine;
        }

        return $this->engine;
    }

    public function render(string $template, array $params = []): string
    {
        return $this->engine()->renderToString($template, $params);
    }
}
