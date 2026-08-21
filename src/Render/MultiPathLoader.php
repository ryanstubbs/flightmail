<?php declare(strict_types=1);

namespace ryanstubbs\FlightMail\Render;

use Latte\Loaders\FileLoader;

/**
 * FileLoader that resolves relative template names against a list of search
 * paths (in order) instead of a single base directory.
 */
final class MultiPathLoader extends FileLoader
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        private readonly array $paths,
    ) {
        parent::__construct();
    }

    public function getContent(string $fileName): string
    {
        return parent::getContent($this->resolve($fileName));
    }

    public function getReferredName(string $file, string $base): string
    {
        if ($this->isAbsolute($file) === true || is_file(dirname($base) . DIRECTORY_SEPARATOR . $file) === true) {
            return parent::getReferredName($file, $base);
        }

        return $this->resolve($file);
    }

    public function getUniqueId(string $fileName): string
    {
        return parent::getUniqueId($this->resolve($fileName));
    }

    private function resolve(string $file): string
    {
        if ($this->isAbsolute($file) === true) {
            return $file;
        }

        foreach ($this->paths as $path) {
            $candidate = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $file;
            if (is_file($candidate) === true) {
                return $candidate;
            }
        }

        // Fall back to the first path so FileLoader raises its usual "missing file" error.
        return rtrim($this->paths[0] ?? '.', '/\\') . DIRECTORY_SEPARATOR . $file;
    }

    private function isAbsolute(string $file): bool
    {
        return str_starts_with($file, '/') === true || preg_match('#^[a-z]:[/\\\\]#i', $file) === 1;
    }
}
