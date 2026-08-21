<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Tests;

use flight\Engine;
use PHPUnit\Framework\TestCase;
use ryanstubbs\FlightMail\Render\RendererInterface;
use ryanstubbs\FlightMail\Tests\Support\CaptureTransportFactory;
use ryanstubbs\FlightMail\Mailer;
use ryanstubbs\FlightMail\MailPlugin;
use ryanstubbs\FlightMail\Message;

final class MailPluginTest extends TestCase
{
    private const TEMPLATES = __DIR__ . '/Fixtures/templates';

    /**
     * @return array<string,mixed>
     */
    private function makeConfig(CaptureTransportFactory $factory): array
    {
        return [
            'dsns' => ['default' => 'capture://default'],
            'transport_factories' => [$factory],
            'templates' => ['paths' => [self::TEMPLATES]],
            'from' => 'no-reply@flightmail.test',
            'renderer' => 'twig',
        ];
    }

    /**
     * @param Engine<object> $app
     */
    private function mailerFromEngine(Engine $app): Mailer
    {
        // Flight's Engine::map() registers mail()/mailer() at runtime.
        /** @phpstan-ignore-next-line */
        return $app->mail();
    }

    public function testInstallRegistersOnGlobalFlightApp(): void
    {
        $factory = new CaptureTransportFactory();
        $plugin = MailPlugin::install($this->makeConfig($factory));

        /** @phpstan-ignore-next-line */
        $mailer = \Flight::mail();
        self::assertInstanceOf(Mailer::class, $mailer);
        self::assertSame($plugin->mailer(), $mailer);
    }

    public function testRegisterExposesMailAccessorsOnEngine(): void
    {
        $app = new Engine();

        $plugin = MailPlugin::register(
            $app,
            $this->makeConfig(new CaptureTransportFactory())
        );

        $mailer = $this->mailerFromEngine($app);

        /** @phpstan-ignore-next-line */
        self::assertSame($mailer, $app->mailer());
        self::assertSame($plugin->mailer(), $mailer);
    }

    public function testSendThroughMappedAccessor(): void
    {
        $factory = new CaptureTransportFactory();
        $app = new Engine();
        MailPlugin::register($app, $this->makeConfig($factory));

        $sent = $this
            ->mailerFromEngine($app)
            ->compose()
            ->to('ryan@example.com')
            ->subject('From Flight')
            ->template('welcome.html.twig', ['name' => 'Ryan'])
            ->send();

        self::assertNotNull($sent);
        self::assertSame(1, $factory->created['default']->sendCount);

        $last = $factory->created['default']->lastMessage;
        self::assertInstanceOf(Message::class, $last);
        self::assertStringContainsString('Hello Ryan!', (string) $last->getHtmlBody());
    }

    public function testAddHookBeforeFirstMailerUse(): void
    {
        $factory = new CaptureTransportFactory();
        $app = new Engine();
        $plugin = MailPlugin::register($app, $this->makeConfig($factory));

        $plugin->addHook(static function (Message $message): void {
            $message->subject('HOOKED');
        });

        $this->mailerFromEngine($app)->compose()->to('ryan@example.com')->text('hi')->send();

        $last = $factory->created['default']->lastMessage;
        self::assertInstanceOf(Message::class, $last);
        self::assertSame('HOOKED', $last->getSubject());
    }

    public function testAddRendererByExtension(): void
    {
        $factory = new CaptureTransportFactory();
        $app = new Engine();
        $plugin = MailPlugin::register($app, $this->makeConfig($factory));

        $plugin->addRenderer('upper', static fn(): RendererInterface => new class implements RendererInterface {
            public function render(string $template, array $params = []): string
            {
                // strip our own ".upper" extension before "rendering"
                return strtoupper(strtr(substr($template, 0, -6), $params));
            }
        });

        $sent = $this
            ->mailerFromEngine($app)
            ->compose()
            ->to('ryan@example.com')
            ->textTemplate('greet {name}.upper', ['{name}' => 'ryan'])
            ->send();

        self::assertNotNull($sent);
        $last = $factory->created['default']->lastMessage;
        self::assertInstanceOf(Message::class, $last);
        self::assertSame('GREET RYAN', $last->getTextBody());
    }
}
