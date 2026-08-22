<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail;

use flight\Engine;
use Psr\Log\LoggerInterface;
use ryanstubbs\FlightMail\Render\RendererFactory;
use ryanstubbs\FlightMail\Transport\TransportManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Closure;

/**
 * FlightPHP plugin registration.
 *
 * Wire it up once in your bootstrap / services file:
 *
 *     MailPlugin::register($app, [
 *         'dsns' => ['default' => 'smtp://user:pass@localhost:1025'],
 *         'renderer' => 'twig',
 *         'templates' => ['paths' => [__DIR__ . '/mail-templates']],
 *         'from' => 'no-reply@example.com',
 *         'inline_css' => true,          // inline <style> blocks (needs pelago/emogrifier)
 *         'text_from_html' => 'markdown' // auto text part from HTML (needs league/html-to-markdown)
 *     ]);
 *
 * Then anywhere in your app:
 *
 *     Flight::mail()->compose()
 *         ->to('someone@example.com')
 *         ->subject('Hello!')
 *         ->template('welcome.twig', ['name' => 'Ryan'])
 *         ->send();
 */
final class MailPlugin
{
    private ?Mailer $mailer = null;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {}

    /**
     * Register the plugin against the global Flight app (Flight::app()),
     * for projects using the classic static style:
     *
     *     require 'vendor/autoload.php';
     *
     *     MailPlugin::install([
     *         'dsns' => ['default' => 'smtp://127.0.0.1:1025'],
     *         'from' => 'no-reply@example.com',
     *     ]);
     *
     *     Flight::route('/', function () {
     *         Flight::mail()->compose()->to('...')->text('hi')->send();
     *     });
     *
     * @param array<string,mixed> $config
     */
    public static function install(array $config = []): self
    {
        return self::register(\Flight::app(), $config);
    }

    /**
     * Register the plugin with a Flight engine. Exposes Flight::mail() and
     * Flight::mailer() accessors and returns the plugin instance for further
     * runtime customization (hooks, custom renderers, custom transports).
     *
     * @param Engine<object>      $app
     * @param array<string,mixed> $config
     */
    public static function register(Engine $app, array $config = []): self
    {
        $plugin = new self($config);

        $app->map('mail', static fn(): Mailer => $plugin->mailer());
        $app->map('mailer', static fn(): Mailer => $plugin->mailer());

        return $plugin;
    }

    public function mailer(): Mailer
    {
        if ($this->mailer === null) {
            $transportManager = new TransportManager($this->config['dsns'] ?? []);
            $transportManager->setDefaultName($this->config['default_transport'] ?? 'default');

            foreach ($this->config['transport_factories'] ?? [] as $factory) {
                $transportManager->addTransportFactory($factory);
            }

            if (isset($this->config['event_dispatcher']) === true) {
                $transportManager->dispatcher($this->config['event_dispatcher']);
            }

            if (isset($this->config['logger']) === true) {
                $transportManager->logger($this->config['logger']);
            }

            $rendererFactory = new RendererFactory($this->config);
            foreach ($this->config['renderers'] ?? [] as $name => $factory) {
                $rendererFactory->add($name, $factory);
            }

            $this->mailer = new Mailer(
                $transportManager,
                $rendererFactory,
                $this->config['renderer'] ?? 'twig',
                $this->config['from'] ?? null,
                $this->config['default_transport'] ?? null,
                $this->config['inline_css'] ?? false,
                $this->config['text_from_html'] ?? false,
            );

            foreach ($this->config['hooks'] ?? [] as $hook) {
                $this->mailer->addHook($hook);
            }
        }

        return $this->mailer;
    }

    /**
     * Add a pre-send hook: fn(Message $message): void. Runs after template
     * rendering and defaults, right before the transport sends.
     */
    public function addHook(callable $hook): static
    {
        $this->mailer()->addHook($hook);

        return $this;
    }

    /**
     * Register a custom template renderer under a name; templates whose file
     * extension matches the name will automatically use it.
     *
     * @param Closure(array<string,mixed>): \ryanstubbs\FlightMail\Render\RendererInterface $factory
     */
    public function addRenderer(string $name, Closure $factory): static
    {
        $this->mailer()->renderers()->add($name, $factory);

        return $this;
    }

    /**
     * Add a custom Symfony transport factory so extra DSN schemes work.
     */
    public function addTransportFactory(TransportFactoryInterface $factory): static
    {
        $this->mailer()->transports()->addTransportFactory($factory);

        return $this;
    }

    public function eventDispatcher(?EventDispatcherInterface $dispatcher): static
    {
        $this->mailer()->transports()->dispatcher($dispatcher);

        return $this;
    }

    public function logger(?LoggerInterface $logger): static
    {
        $this->mailer()->transports()->logger($logger);

        return $this;
    }
}
