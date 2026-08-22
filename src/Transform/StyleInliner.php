<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Transform;

use Pelago\Emogrifier\CssInliner;
use LogicException;
use RuntimeException;

/**
 * Inlines CSS from <style> blocks - plus any extra stylesheet you supply -
 * into style="" attributes, powered by pelago/emogrifier.
 *
 * Most webmail clients (Gmail included) strip <style> blocks, so inlining is
 * the only reliable way to style HTML email. The library is optional: it is
 * only required when inlining is actually used.
 */
final class StyleInliner
{
    /**
     * @param array{css?:string,css_file?:string}|bool $options true processes the <style>
     *                                                          blocks already present in each message; an array can additionally inject CSS via
     *                                                          "css" (raw rules) and/or "css_file" (path to a stylesheet shared by all messages).
     */
    public function __construct(private readonly bool|array $options = false) {}

    /**
     * Whether the optional dependency is installed.
     */
    public static function available(): bool
    {
        return class_exists(CssInliner::class);
    }

    /**
     * Return $html with its CSS inlined. Documents keep their full structure;
     * fragments are returned as body content without added wrappers.
     */
    public function inline(string $html): string
    {
        if (self::available() === false) {
            throw new LogicException(
                'CSS inlining requires pelago/emogrifier. Install it with: composer require pelago/emogrifier'
            );
        }

        $inliner = CssInliner::fromHtml($html)->inlineCss($this->extraCss());

        return stripos($html, '<body') !== false
            ? $inliner->render()
            : $inliner->renderBodyContent();
    }

    private function extraCss(): string
    {
        if (is_bool($this->options) === true) {
            return '';
        }

        $css = trim((string) ($this->options['css'] ?? ''));
        $file = $this->options['css_file'] ?? null;

        if (is_string($file) === true && $file !== '') {
            if (is_file($file) === false || ($contents = file_get_contents($file)) === false) {
                throw new RuntimeException(sprintf('The "inline_css" css_file "%s" could not be read.', $file));
            }

            $css = trim($css . "\n" . $contents);
        }

        return $css;
    }
}
