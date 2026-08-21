<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Tests;

use PHPUnit\Framework\TestCase;
use ryanstubbs\FlightMail\Render\RendererFactory;
use ryanstubbs\FlightMail\Tests\Support\CaptureTransportFactory;
use ryanstubbs\FlightMail\Transport\TransportManager;
use ryanstubbs\FlightMail\Mailer;
use ryanstubbs\FlightMail\Message;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;

final class MailerTest extends TestCase
{
    private const TEMPLATES = __DIR__ . '/Fixtures/templates';

    /**
     * @param array<string,string>                         $dsns
     * @param Address|string|array<int|string,string>|null $from
     */
    private function makeMailer(
        array $dsns = ['default' => 'null://null'],
        ?string $defaultRenderer = 'twig',
        Address|string|array|null $from = 'no-reply@flightmail.test',
        ?string $defaultTransport = null,
        ?CaptureTransportFactory $captureFactory = null,
    ): Mailer {
        $manager = new TransportManager($dsns);
        if ($captureFactory !== null) {
            $manager->addTransportFactory($captureFactory);
        }

        return new Mailer(
            $manager,
            new RendererFactory(['templates' => ['paths' => [self::TEMPLATES]]]),
            $defaultRenderer ?? 'twig',
            $from,
            $defaultTransport,
        );
    }

    public function testSendWithNullTransportReturnsSentMessage(): void
    {
        $mailer = $this->makeMailer();

        $sent = $mailer
            ->compose()
            ->from('no-reply@example.com')
            ->to('ryan@example.com')
            ->subject('Test')
            ->text('Plain body')
            ->send();

        self::assertInstanceOf(SentMessage::class, $sent);
        self::assertStringContainsString('Plain body', (string) $sent->toString());
    }

    public function testSendPlainStringsWithoutAnyRenderer(): void
    {
        // No template paths, no renderer config — strings only.
        $mailer = new Mailer(
            new TransportManager(['default' => 'null://null']),
            new RendererFactory([]),
        );

        $sent = $mailer
            ->compose()
            ->from('no-reply@flightmail.test')
            ->to('ryan@example.com')
            ->subject('No templates here')
            ->html('<p>raw html</p>')
            ->text('raw text')
            ->send();

        self::assertInstanceOf(SentMessage::class, $sent);
        self::assertStringContainsString('raw html', (string) $sent->toString());
        self::assertStringContainsString('raw text', (string) $sent->toString());
    }

    public function testTemplatesAreRenderedLazilyAtSendTime(): void
    {
        $mailer = $this->makeMailer();

        $message = $mailer
            ->compose()
            ->to('ryan@example.com')
            ->template('welcome.html.twig', ['name' => 'Ryan'])
            ->textTemplate('welcome.txt.twig', ['name' => 'Ryan']);

        // Nothing rendered yet.
        self::assertNull($message->getHtmlBody());

        $sent = $mailer->send($message);

        self::assertInstanceOf(SentMessage::class, $sent);
        self::assertStringContainsString('Hello Ryan!', (string) $sent->toString());
        self::assertStringContainsString('TEXT Ryan', (string) $sent->toString());
    }

    public function testLatteTemplateUsedByExtensionEvenWhenTwigIsDefault(): void
    {
        $mailer = $this->makeMailer(defaultRenderer: 'twig');

        $sent = $mailer
            ->compose()
            ->to('ryan@example.com')
            ->template('welcome.latte', ['name' => 'Ryan'])
            ->send();

        self::assertInstanceOf(SentMessage::class, $sent);
        self::assertStringContainsString('Hello Ryan!', (string) $sent->toString());
    }

    public function testExplicitHtmlBodyWinsOverTemplate(): void
    {
        $mailer = $this->makeMailer();

        $sent = $mailer
            ->compose()
            ->to('ryan@example.com')
            ->html('<p>explicit</p>')
            ->template('welcome.html.twig', ['name' => 'Ryan'])
            ->send();

        self::assertInstanceOf(SentMessage::class, $sent);
        self::assertStringContainsString('explicit', (string) $sent->toString());
        self::assertStringNotContainsString('Hello Ryan!', (string) $sent->toString());
    }

    public function testDefaultFromAppliedWhenMissing(): void
    {
        $mailer = $this->makeMailer(from: ['no-reply@flightmail.test' => 'FlightMail']);

        $sent = $mailer
            ->compose()
            ->to('ryan@example.com')
            ->text('hi')
            ->send();

        self::assertInstanceOf(SentMessage::class, $sent);
        self::assertSame('no-reply@flightmail.test', $sent->getEnvelope()->getSender()->getAddress());
    }

    public function testExplicitFromBeatsDefault(): void
    {
        $mailer = $this->makeMailer(from: 'no-reply@flightmail.test');

        $sent = $mailer
            ->compose()
            ->from('me@flightmail.test')
            ->to('ryan@example.com')
            ->text('hi')
            ->send();

        self::assertInstanceOf(SentMessage::class, $sent);
        self::assertSame('me@flightmail.test', $sent->getEnvelope()->getSender()->getAddress());
    }

    public function testHooksRunBeforeSend(): void
    {
        $mailer = $this->makeMailer();
        $seen = null;
        $mailer->addHook(static function (Message $message) use (&$seen): void {
            $seen = $message;
            $message->subject('HOOKED');
        });

        $sent = $mailer
            ->compose()
            ->to('ryan@example.com')
            ->text('hi')
            ->send();

        self::assertInstanceOf(Message::class, $seen);
        self::assertInstanceOf(SentMessage::class, $sent);
        $original = $sent->getOriginalMessage();
        self::assertInstanceOf(Message::class, $original);
        self::assertSame('HOOKED', $original->getSubject());
    }

    public function testRenderDirectlyUsesDefaultRenderer(): void
    {
        $mailer = $this->makeMailer(defaultRenderer: 'latte');

        self::assertSame('Hello Ryan!', trim($mailer->render('welcome.latte', ['name' => 'Ryan'])));
    }

    public function testDefaultTransportRoutesWhenSetAndDifferentFromFirst(): void
    {
        $factory = new CaptureTransportFactory();
        $mailer = $this->makeMailer(
            dsns: ['first' => 'capture://first', 'second' => 'capture://second'],
            defaultTransport: 'second',
            captureFactory: $factory,
        );

        $mailer->compose()->to('ryan@example.com')->text('hi')->send();

        self::assertSame(0, $factory->created['first']->sendCount);
        self::assertSame(1, $factory->created['second']->sendCount);
    }

    public function testNoHeaderNeededForFirstTransport(): void
    {
        $factory = new CaptureTransportFactory();
        $mailer = $this->makeMailer(
            dsns: ['first' => 'capture://first', 'second' => 'capture://second'],
            captureFactory: $factory,
        );

        $mailer->compose()->to('ryan@example.com')->text('hi')->send();

        self::assertSame(1, $factory->created['first']->sendCount);
        self::assertSame(0, $factory->created['second']->sendCount);
    }

    public function testExplicitTransportOverridesDefault(): void
    {
        $factory = new CaptureTransportFactory();
        $mailer = $this->makeMailer(
            dsns: ['first' => 'capture://first', 'second' => 'capture://second'],
            defaultTransport: 'second',
            captureFactory: $factory,
        );

        $mailer->compose()->to('ryan@example.com')->text('hi')->transport('first')->send();

        self::assertSame(1, $factory->created['first']->sendCount);
        self::assertSame(0, $factory->created['second']->sendCount);
    }
}
