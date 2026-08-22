<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Tests;

use PHPUnit\Framework\TestCase;
use ryanstubbs\FlightMail\Render\RendererFactory;
use ryanstubbs\FlightMail\Tests\Support\CaptureTransport;
use ryanstubbs\FlightMail\Tests\Support\CaptureTransportFactory;
use ryanstubbs\FlightMail\Transform\HtmlToText;
use ryanstubbs\FlightMail\Transform\StyleInliner;
use ryanstubbs\FlightMail\Transport\TransportManager;
use ryanstubbs\FlightMail\Mailer;
use RuntimeException;
use InvalidArgumentException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

final class BodyEnhancementTest extends TestCase
{
    private const TEMPLATES = __DIR__ . '/Fixtures/templates';

    private CaptureTransportFactory $captureFactory;

    /**
     * @param array{css?:string,css_file?:string}|bool $inlineCss
     */
    private function makeMailer(
        bool|array $inlineCss = false,
        bool|string $textFromHtml = false,
    ): Mailer {
        $this->captureFactory = new CaptureTransportFactory();

        $manager = new TransportManager(['default' => 'capture://default']);
        $manager->addTransportFactory($this->captureFactory);

        return new Mailer(
            $manager,
            new RendererFactory(['templates' => ['paths' => [self::TEMPLATES]]]),
            'twig',
            'no-reply@flightmail.test',
            null,
            $inlineCss,
            $textFromHtml,
        );
    }

    /**
     * @return array{SentMessage, Email}
     */
    private function sendHtml(Mailer $mailer, string $html): array
    {
        $sent = $mailer->compose()
            ->from('no-reply@flightmail.test')
            ->to('ryan@example.com')
            ->subject('Enhance me')
            ->html($html)
            ->transport('default')
            ->send();

        self::assertInstanceOf(SentMessage::class, $sent);
        $message = $sent->getOriginalMessage();
        self::assertInstanceOf(Email::class, $message);

        return [$sent, $message];
    }

    private function sentHtmlBody(): string
    {
        $transport = $this->captureFactory->created['default'] ?? null;
        self::assertInstanceOf(CaptureTransport::class, $transport);
        $captured = $transport->lastMessage;
        self::assertInstanceOf(Email::class, $captured);

        return (string) $captured->getHtmlBody();
    }

    // ---------------------------------------------------------------- inlining

    public function testInlineCssAddsStyleAttributesWhenGloballyEnabled(): void
    {
        $mailer = $this->makeMailer(inlineCss: true);

        [, $message] = $this->sendHtml($mailer, '<style>p { color: red; }</style><p>Hello <strong>Ryan</strong></p>');

        self::assertStringContainsString('style="color: red;"', (string) $message->getHtmlBody());
        self::assertSame($message->getHtmlBody(), $this->sentHtmlBody());
    }

    public function testInlineCssDisabledByDefault(): void
    {
        $mailer = $this->makeMailer();

        [, $message] = $this->sendHtml($mailer, '<style>p { color: red; }</style><p>hi</p>');

        self::assertStringNotContainsString('style=', $this->sentHtmlBody());
        self::assertStringContainsString('<style>', $this->sentHtmlBody());
        self::assertNull($message->getTextBody());
    }

    public function testMessageCanOptInToInliningWhenGloballyOff(): void
    {
        $mailer = $this->makeMailer();

        $mailer->compose()
            ->from('no-reply@flightmail.test')
            ->to('ryan@example.com')
            ->subject('Opt in')
            ->html('<style>p { color: red; }</style><p>hi</p>')
            ->inlineCss()
            ->send();

        self::assertStringContainsString('style="color: red;"', $this->sentHtmlBody());
    }

    public function testMessageCanSkipInliningWhenGloballyOn(): void
    {
        $mailer = $this->makeMailer(inlineCss: true);

        $mailer->compose()
            ->from('no-reply@flightmail.test')
            ->to('ryan@example.com')
            ->subject('Opt out')
            ->html('<style>p { color: red; }</style><p>hi</p>')
            ->withoutInlineCss()
            ->send();

        self::assertStringNotContainsString('style=', $this->sentHtmlBody());
    }

    public function testExtraCssFromArrayIsInjected(): void
    {
        $inliner = new StyleInliner(['css' => '.cta { background: blue; }']);

        $result = $inliner->inline('<a class="cta" href="https://example.com">Go</a>');

        self::assertSame('<a class="cta" href="https://example.com" style="background: blue;">Go</a>', $result);
    }

    public function testExtraCssFileIsRead(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'flightmail-css');
        file_put_contents($path, 'b { font-weight: 900; }');

        try {
            $inliner = new StyleInliner(['css_file' => $path]);
            $result = $inliner->inline('<b>bold</b>');
        } finally {
            unlink($path);
        }

        self::assertSame('<b style="font-weight: 900;">bold</b>', $result);
    }

    public function testMissingCssFileThrows(): void
    {
        $inliner = new StyleInliner(['css_file' => '/nonexistent/base.css']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('/nonexistent/base.css');

        $inliner->inline('<p>hi</p>');
    }

    public function testInliningPreservesFullDocumentShape(): void
    {
        $inliner = new StyleInliner();
        $document = '<!DOCTYPE html><html><head><title>T</title></head><body style="margin:0"><p>x</p></body></html>';

        $result = $inliner->inline($document);

        self::assertStringContainsString('<!DOCTYPE html>', $result);
        self::assertStringContainsString('style="margin: 0;"', $result);

        $fragment = (new StyleInliner())->inline('<p style="margin:0">x</p>');
        self::assertStringNotContainsString('<html', $fragment);
        self::assertStringContainsString('style="margin: 0;"', $fragment);
    }

    // ------------------------------------------------------------ text from html

    public function testMarkdownTextPartGeneratedWhenConfigured(): void
    {
        $mailer = $this->makeMailer(textFromHtml: HtmlToText::MARKDOWN);

        [, $message] = $this->sendHtml(
            $mailer,
            '<style>p { color: red; }</style><h1>Welcome</h1><p>Hello <strong>Ryan</strong></p>',
        );

        $text = (string) $message->getTextBody();

        self::assertStringContainsString('**Ryan**', $text);
        self::assertStringNotContainsString('<strong>', $text);
        self::assertStringNotContainsString('color', $text); // <style> block removed
    }

    public function testPlainModeStripsTags(): void
    {
        $mailer = $this->makeMailer(textFromHtml: HtmlToText::PLAIN);

        [, $message] = $this->sendHtml($mailer, '<style>p { color: red; }</style><p>Hello Ryan</p>');

        $text = (string) $message->getTextBody();

        self::assertStringContainsString('Hello Ryan', $text);
        self::assertStringNotContainsString('color', $text);
    }

    public function testAutoModePrefersMarkdownWhenAvailable(): void
    {
        $mailer = $this->makeMailer(textFromHtml: true);

        [, $message] = $this->sendHtml($mailer, '<p>Hello <strong>Ryan</strong></p>');

        self::assertStringContainsString('**Ryan**', (string) $message->getTextBody());
    }

    public function testExplicitTextBodyWinsOverGeneration(): void
    {
        $mailer = $this->makeMailer(textFromHtml: true);

        $sent = $mailer->compose()
            ->from('no-reply@flightmail.test')
            ->to('ryan@example.com')
            ->subject('Both parts')
            ->html('<p>rich</p>')
            ->text('hand written')
            ->send();

        self::assertInstanceOf(SentMessage::class, $sent);
        $message = $sent->getOriginalMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('hand written', $message->getTextBody());
    }

    public function testNoTextGeneratedWithoutHtmlBody(): void
    {
        $mailer = $this->makeMailer(textFromHtml: true);

        $sent = $mailer->compose()
            ->from('no-reply@flightmail.test')
            ->to('ryan@example.com')
            ->subject('Text only')
            ->text('just text')
            ->send();

        self::assertInstanceOf(SentMessage::class, $sent);
        $message = $sent->getOriginalMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('just text', $message->getTextBody());
    }

    public function testMessageOverrideForcesPlainOverGlobalMarkdown(): void
    {
        $mailer = $this->makeMailer(textFromHtml: HtmlToText::MARKDOWN);

        $sent = $mailer->compose()
            ->from('no-reply@flightmail.test')
            ->to('ryan@example.com')
            ->subject('Per message format')
            ->html('<h1>Hi</h1><p><strong>bold</strong></p>')
            ->textFromHtml(HtmlToText::PLAIN)
            ->send();

        self::assertInstanceOf(SentMessage::class, $sent);
        $message = $sent->getOriginalMessage();
        self::assertInstanceOf(Email::class, $message);
        $text = (string) $message->getTextBody();

        self::assertStringContainsString('bold', $text);
        self::assertStringNotContainsString('**bold**', $text);
    }

    public function testMessageCanDisableGeneration(): void
    {
        $mailer = $this->makeMailer(textFromHtml: true);

        [, $message] = $this->sendHtml($mailer, '<p>rich</p>');
        self::assertNotNull($message->getTextBody());

        $optOut = $this->makeMailer(textFromHtml: true);
        $sent = $optOut->compose()
            ->from('no-reply@flightmail.test')
            ->to('ryan@example.com')
            ->subject('Html only')
            ->html('<p>rich</p>')
            ->withoutTextFromHtml()
            ->send();

        self::assertInstanceOf(SentMessage::class, $sent);
        $outMessage = $sent->getOriginalMessage();
        self::assertInstanceOf(Email::class, $outMessage);
        self::assertNull($outMessage->getTextBody());
    }

    public function testInvalidConverterModeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HtmlToText('richtext');
    }

    public function testInvalidGlobalTextModeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeMailer(textFromHtml: 'markdwon');
    }
}
