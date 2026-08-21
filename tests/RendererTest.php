<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Tests;

use PHPUnit\Framework\TestCase;
use ryanstubbs\FlightMail\Render\LatteRenderer;
use ryanstubbs\FlightMail\Render\MultiPathLoader;
use ryanstubbs\FlightMail\Render\RendererFactory;
use ryanstubbs\FlightMail\Render\TwigRenderer;

final class RendererTest extends TestCase
{
    private const TEMPLATES = __DIR__ . '/Fixtures/templates';

    public function testTwigRendersTemplateWithParams(): void
    {
        $renderer = new TwigRenderer([self::TEMPLATES]);

        self::assertSame('Hello Ryan!', trim($renderer->render('welcome.html.twig', ['name' => 'Ryan'])));
    }

    public function testLatteRendersTemplateWithParams(): void
    {
        $renderer = new LatteRenderer([self::TEMPLATES]);

        self::assertSame('Hello Ryan!', trim($renderer->render('welcome.latte', ['name' => 'Ryan'])));
    }

    public function testMultiPathLoaderResolvesAcrossPaths(): void
    {
        $renderer = new LatteRenderer([self::TEMPLATES, __DIR__ . '/Fixtures/templates_alt']);

        self::assertSame('SECOND Ryan', trim($renderer->render('second.latte', ['name' => 'Ryan'])));
    }

    public function testFactoryCreatesCachedInstances(): void
    {
        $factory = new RendererFactory([
            'templates' => ['paths' => [self::TEMPLATES]],
        ]);

        self::assertTrue($factory->has('twig'));
        self::assertTrue($factory->has('latte'));

        $first = $factory->create('twig');
        self::assertSame($first, $factory->create('twig'));
        self::assertInstanceOf(TwigRenderer::class, $first);
        self::assertInstanceOf(LatteRenderer::class, $factory->create('latte'));
    }

    public function testCustomRendererCanBeRegistered(): void
    {
        $factory = new RendererFactory();
        $factory->add('upper', static fn(): \ryanstubbs\FlightMail\Render\RendererInterface => new class implements \ryanstubbs\FlightMail\Render\RendererInterface {
            public function render(string $template, array $params = []): string
            {
                return strtoupper(strtr($template, $params));
            }
        });

        self::assertTrue($factory->has('upper'));
        self::assertSame('HELLO RYAN', $factory->create('upper')->render('hello {name}', ['{name}' => 'ryan']));
    }

    public function testUnknownRendererThrows(): void
    {
        $factory = new RendererFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown mail template renderer "markdown"');
        $factory->create('markdown');
    }

    public function testMultiPathLoaderUniqueIdStable(): void
    {
        $loader = new MultiPathLoader([self::TEMPLATES]);

        self::assertSame(
            $loader->getUniqueId('welcome.latte'),
            $loader->getUniqueId('welcome.latte'),
        );
    }
}
