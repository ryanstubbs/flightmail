<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Transform;

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Mime\HtmlToTextConverter\DefaultHtmlToTextConverter;
use Symfony\Component\Mime\HtmlToTextConverter\LeagueHtmlToMarkdownConverter;
use InvalidArgumentException;
use LogicException;

/**
 * Converts an HTML body into the text part of an email.
 *
 * "plain" strips tags (always available, ships with symfony/mime); "markdown"
 * produces readable Markdown via league/html-to-markdown, which is optional.
 */
final class HtmlToText
{
    public const PLAIN = 'plain';

    public const MARKDOWN = 'markdown';

    /**
     * Whether the optional Markdown dependency is installed.
     */
    public static function markdownAvailable(): bool
    {
        return class_exists(HtmlConverter::class);
    }

    public function __construct(private readonly string $mode = self::PLAIN)
    {
        if (in_array($mode, [self::PLAIN, self::MARKDOWN], true) === false) {
            throw new InvalidArgumentException(sprintf(
                'Unknown text mode "%s" - expected "%s" or "%s".',
                $mode,
                self::PLAIN,
                self::MARKDOWN,
            ));
        }
    }

    /**
     * @param string $charset only used to satisfy the converter interface; output uses it too
     */
    public function convert(string $html, string $charset = 'utf-8'): string
    {
        return $this->mode === self::MARKDOWN
            ? $this->markdownConverter()->convert($html, $charset)
            : (new DefaultHtmlToTextConverter())->convert($html, $charset);
    }

    private function markdownConverter(): LeagueHtmlToMarkdownConverter
    {
        if (self::markdownAvailable() === false) {
            throw new LogicException(
                'Markdown text parts require league/html-to-markdown. '
                . 'Install it with: composer require league/html-to-markdown'
            );
        }

        return new LeagueHtmlToMarkdownConverter();
    }
}
