<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Render;

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use RuntimeException;

final class TwigRenderer implements RendererInterface
{
    private ?Environment $environment = null;

    /**
     * @param list<string>        $paths     template search paths, searched in order
     * @param string|null         $cachePath compilation cache directory (null = Twig default)
     * @param array<string,mixed> $options   extra options passed to Twig\Environment
     */
    public function __construct(
        private readonly array $paths,
        private readonly ?string $cachePath = null,
        private readonly array $options = [],
    ) {}

    public static function isAvailable(): bool
    {
        return class_exists(Environment::class);
    }

    public static function notInstalled(): RuntimeException
    {
        return new RuntimeException(
            'Twig is not installed. Run "composer require twig/twig" or choose another renderer.'
        );
    }

    public function environment(): Environment
    {
        if ($this->environment === null) {
            if (self::isAvailable() === false) {
                throw self::notInstalled();
            }

            $loader = new FilesystemLoader();
            foreach ($this->paths as $path) {
                $loader->addPath($path);
            }

            $options = $this->options;
            if ($this->cachePath !== null && isset($options['cache']) === false) {
                $options['cache'] = $this->cachePath;
            }

            $this->environment = new Environment($loader, $options);
        }

        return $this->environment;
    }

    public function render(string $template, array $params = []): string
    {
        return $this->environment()->render($template, $params);
    }
}
