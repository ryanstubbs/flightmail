<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail;

use ryanstubbs\FlightMail\Render\RendererFactory;
use ryanstubbs\FlightMail\Render\RendererInterface;
use ryanstubbs\FlightMail\Transport\TransportManager;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;

/**
 * The main entry point for sending mail. Wraps Symfony Mailer transports and adds:
 *
 *  - lazy template rendering (Twig / Latte / custom) at send time
 *  - named multi-provider transports selected per message
 *  - global defaults (From) and pre-send hooks
 */
final class Mailer
{
    /**
     * @var list<callable(Message): void>
     */
    private array $hooks = [];

    private ?Address $defaultFrom = null;

    /**
     * @param TransportManager                   $transports
     * @param RendererFactory                    $renderers
     * @param Address|string|array<int|string,string>|null $defaultFrom
     */
    public function __construct(
        private readonly TransportManager $transports,
        private readonly RendererFactory $renderers,
        private readonly string $defaultRenderer = 'twig',
        Address|string|array|null $defaultFrom = null,
        private readonly ?string $defaultTransport = null,
    ) {
        $this->defaultFrom = self::normalizeAddress($defaultFrom);
    }

    /**
     * Start building a new message.
     */
    public function compose(): Message
    {
        $message = new Message();
        $message->setMailer($this);

        return $message;
    }

    public function addHook(callable $hook): static
    {
        $this->hooks[] = $hook;

        return $this;
    }

    public function transports(): TransportManager
    {
        return $this->transports;
    }

    public function renderers(): RendererFactory
    {
        return $this->renderers;
    }

    /**
     * Render a template directly using the given (or default) renderer.
     *
     * @param array<string,mixed> $params
     */
    public function render(string $template, array $params = [], ?string $renderer = null): string
    {
        return $this->renderer($renderer ?? $this->rendererNameFor($template))->render($template, $params);
    }

    public function renderer(?string $name = null): RendererInterface
    {
        return $this->renderers->create($name ?? $this->defaultRenderer);
    }

    /**
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function send(Message $message, ?Envelope $envelope = null): ?SentMessage
    {
        $this->renderTemplates($message);
        $this->applyDefaults($message);

        foreach ($this->hooks as $hook) {
            $hook($message);
        }

        return $this->transports->all()->send($message, $envelope);
    }

    private function renderTemplates(Message $message): void
    {
        $htmlTemplate = $message->getHtmlTemplateName();
        if ($htmlTemplate !== null && self::isEmptyBody($message->getHtmlBody()) === true) {
            $message->html($this->render($htmlTemplate, $message->getHtmlTemplateParams()));
        }

        $textTemplate = $message->getTextTemplateName();
        if ($textTemplate !== null && self::isEmptyBody($message->getTextBody()) === true) {
            $message->text($this->render($textTemplate, $message->getTextTemplateParams()));
        }
    }

    private function applyDefaults(Message $message): void
    {
        if ($this->defaultFrom !== null && $message->getFrom() === []) {
            $message->from($this->defaultFrom);
        }

        if (
            $this->defaultTransport !== null &&
            $message->getTransportName() === null &&
            $this->defaultTransport !== ($this->transports->names()[0] ?? null)
        ) {
            $message->transport($this->defaultTransport);
        }
    }

    /**
     * Pick a renderer by template extension when one is registered under that
     * name (.twig -> "twig", .latte -> "latte", .md -> "markdown", ...),
     * otherwise fall back to the configured default renderer.
     */
    private function rendererNameFor(string $template): string
    {
        $extension = strtolower(pathinfo($template, PATHINFO_EXTENSION));

        return $this->renderers->has($extension) === true ? $extension : $this->defaultRenderer;
    }

    /**
     * @param string|list<mixed>|null $body
     */
    private static function isEmptyBody(string|array|null $body): bool
    {
        return $body === null || $body === '' || $body === [];
    }

    /**
     * @param Address|string|array<int|string,string>|null $from
     */
    private static function normalizeAddress(Address|string|array|null $from): ?Address
    {
        if ($from === null || $from instanceof Address) {
            return $from;
        }

        if (is_string($from) === true) {
            return new Address($from);
        }

        $email = array_key_first($from);
        $value = $from[$email];

        return is_int($email) === true
            ? new Address((string) $value)
            : new Address((string) $email, (string) $value);
    }
}
