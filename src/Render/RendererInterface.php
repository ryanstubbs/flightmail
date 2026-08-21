<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Render;

interface RendererInterface
{
    /**
     * Render a template with the given parameters and return the result as a string.
     *
     * @param array<string,mixed> $params
     */
    public function render(string $template, array $params = []): string;
}
