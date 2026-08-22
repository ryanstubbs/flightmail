<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail;

use ryanstubbs\FlightMail\Transform\HtmlToText;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Email;
use InvalidArgumentException;
use LogicException;

/**
 * A Symfony Mime Email that knows about templates and named transports.
 *
 * Template bodies are rendered lazily by the Mailer at send time, so you can
 * compose messages long before a renderer or transport is available.
 */
final class Message extends Email
{
    private ?Mailer $mailer = null;

    private ?string $htmlTemplateName = null;

    /**
     * @var array<string,mixed>
     */
    private array $htmlTemplateParams = [];

    private ?string $textTemplateName = null;

    /**
     * @var array<string,mixed>
     */
    private array $textTemplateParams = [];

    private ?bool $inlineCssOverride = null;

    /**
     * true = follow the mailer-wide format, "plain"/"markdown" force one,
     * false = never generate, null = follow global configuration.
     *
     * @var bool|string|null
     */
    private $textFromHtmlOverride = null;

    /**
     * Render this template into the HTML body (alias of htmlTemplate()).
     *
     * @param array<string,mixed> $params
     */
    public function template(string $template, array $params = []): static
    {
        return $this->htmlTemplate($template, $params);
    }

    /**
     * Render this template into the HTML body.
     *
     * @param array<string,mixed> $params
     */
    public function htmlTemplate(string $template, array $params = []): static
    {
        $this->htmlTemplateName = $template;
        $this->htmlTemplateParams = $params;

        return $this;
    }

    /**
     * Render this template into the text body.
     *
     * @param array<string,mixed> $params
     */
    public function textTemplate(string $template, array $params = []): static
    {
        $this->textTemplateName = $template;
        $this->textTemplateParams = $params;

        return $this;
    }

    /**
     * Force CSS inlining on or off for this message, overriding the mailer's
     * "inline_css" setting.
     */
    public function inlineCss(bool $enabled = true): static
    {
        $this->inlineCssOverride = $enabled;

        return $this;
    }

    /**
     * Never inline CSS into this message.
     */
    public function withoutInlineCss(): static
    {
        return $this->inlineCss(false);
    }

    /**
     * Generate this message's text part from its HTML body at send time,
     * overriding the mailer's "text_from_html" setting. Pass true to follow
     * the configured format, "auto" / "plain" / "markdown" to force one, or
     * false to disable generation for this message.
     *
     * @param bool|string $mode true, false, "auto", "plain" or "markdown"
     */
    public function textFromHtml(bool|string $mode = true): static
    {
        if (
            is_string($mode) === true &&
            in_array($mode, ['auto', HtmlToText::PLAIN, HtmlToText::MARKDOWN], true) === false
        ) {
            throw new InvalidArgumentException(sprintf(
                'textFromHtml expects true, false, "auto", "%s" or "%s" (got "%s").',
                HtmlToText::PLAIN,
                HtmlToText::MARKDOWN,
                $mode,
            ));
        }

        $this->textFromHtmlOverride = $mode;

        return $this;
    }

    /**
     * Never generate a text part from HTML for this message.
     */
    public function withoutTextFromHtml(): static
    {
        return $this->textFromHtml(false);
    }

    /**
     * @internal read by the Mailer when applying body enhancements
     */
    public function getInlineCssOverride(): ?bool
    {
        return $this->inlineCssOverride;
    }

    /**
     * @internal read by the Mailer when applying body enhancements
     */
    public function getTextFromHtmlOverride(): bool|string|null
    {
        return $this->textFromHtmlOverride;
    }

    /**
     * Route this message through a named transport configured in "dsns".
     */
    public function transport(string $name): static
    {
        $this->getHeaders()->addTextHeader('X-Transport', $name);

        return $this;
    }

    /**
     * Send this message through the Mailer that created it.
     */
    public function send(?Envelope $envelope = null): ?SentMessage
    {
        if ($this->mailer === null) {
            throw new LogicException(
                'This message is not bound to a Mailer. Create messages via $mailer->compose() '
                . 'or send them explicitly with $mailer->send($message).'
            );
        }

        return $this->mailer->send($this, $envelope);
    }

    /**
     * @internal called by Mailer::compose()
     */
    public function setMailer(Mailer $mailer): void
    {
        $this->mailer = $mailer;
    }

    public function getHtmlTemplateName(): ?string
    {
        return $this->htmlTemplateName;
    }

    /**
     * @return array<string,mixed>
     */
    public function getHtmlTemplateParams(): array
    {
        return $this->htmlTemplateParams;
    }

    public function getTextTemplateName(): ?string
    {
        return $this->textTemplateName;
    }

    /**
     * @return array<string,mixed>
     */
    public function getTextTemplateParams(): array
    {
        return $this->textTemplateParams;
    }

    public function hasTemplates(): bool
    {
        return $this->htmlTemplateName !== null || $this->textTemplateName !== null;
    }

    /**
     * @internal used by Mailer to avoid double-rendering when a header already exists
     */
    public function getTransportName(): ?string
    {
        /** @var HeaderInterface|null $header */
        $header = $this->getHeaders()->get('X-Transport');

        return $header?->getBodyAsString();
    }
}
