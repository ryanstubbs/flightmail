<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Tests;

use PHPUnit\Framework\TestCase;
use ryanstubbs\FlightMail\Message;

final class MessageTest extends TestCase
{
    public function testTemplateIsAliasForHtmlTemplate(): void
    {
        $message = (new Message())
            ->template('welcome.html.twig', ['name' => 'Ryan']);

        self::assertSame('welcome.html.twig', $message->getHtmlTemplateName());
        self::assertSame(['name' => 'Ryan'], $message->getHtmlTemplateParams());
        self::assertTrue($message->hasTemplates());
    }

    public function testTextTemplate(): void
    {
        $message = (new Message())
            ->textTemplate('welcome.txt.twig', ['name' => 'Ryan']);

        self::assertSame('welcome.txt.twig', $message->getTextTemplateName());
        self::assertSame(['name' => 'Ryan'], $message->getTextTemplateParams());
        self::assertNull($message->getHtmlTemplateName());
        self::assertTrue($message->hasTemplates());
    }

    public function testTransportSetsHeader(): void
    {
        $message = (new Message())->transport('postmark');

        self::assertSame('postmark', $message->getTransportName());
    }

    public function testNoTransportByDefault(): void
    {
        self::assertNull((new Message())->getTransportName());
    }

    public function testFluentChainingWithSymfonyMethods(): void
    {
        $message = (new Message())
            ->to('to@example.com')
            ->subject('Hi')
            ->template('welcome.latte', ['name' => 'Ryan'])
            ->transport('backup');

        self::assertSame(['to@example.com'], array_map(
            static fn($address): string => $address->getAddress(),
            $message->getTo(),
        ));
        self::assertSame('Hi', $message->getSubject());
        self::assertSame('backup', $message->getTransportName());
    }
}
