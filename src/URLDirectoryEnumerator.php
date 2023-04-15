<?php

namespace Sabatier\Foundation;

use Closure;
use Exception;
use FilesystemIterator;
use Generator;
use JetBrains\PhpStorm\ExpectedValues;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Traversable;

/**
 * @extends DirectoryEnumerator<URL>
 * @internal
 */
class URLDirectoryEnumerator extends DirectoryEnumerator
{
    private readonly RecursiveIteratorIterator $iterator;
    private ?URL $currentURL = null;
    private bool $shouldContinue = false;
    private bool $isPostOrderDirectory = false;

    public function __construct(private readonly URL $url, private readonly ?ArrayClass $keys = null, #[ExpectedValues(flagsFromClass: DirectoryEnumerationOptions::class)] private readonly int $options = DirectoryEnumerationOptions::skipsHiddenFiles, private readonly ?Closure $errorHandler = null)
    {
        $this->iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->url->path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    }

    public function directoryAttributes(): ?Dictionary
    {
        try {
            return FileManager::default()->attributesOfItem($this->url->path);
        } catch (Exception) {
            return null;
        }
    }

    public function fileAttributes(): ?Dictionary
    {
        if ($currentURL = $this->currentURL) {
            try {
                return FileManager::default()->attributesOfItem($currentURL->path);
            } catch (Exception) {
                return null;
            }
        }
        return null;
    }

    public function level(): int
    {
        return $this->iterator->getDepth();
    }

    public function skipDescendants(): void
    {
        $this->shouldContinue = true;
    }

    public function isEnumeratingDirectoryPostOrder(): bool
    {
        return $this->isPostOrderDirectory;
    }

    public function getIterator(): Traversable
    {
        return (function (): Generator {
            foreach ($this->iterator as $path) {
                if ((($this->options & DirectoryEnumerationOptions::skipsSubdirectoryDescendants || $this->options & DirectoryEnumerationOptions::skipsPackageDescendants) && is_dir($path) && !pathinfo($path, PATHINFO_EXTENSION)) || (($this->options & DirectoryEnumerationOptions::skipsHiddenFiles) && is_hidden($path))) {
                    continue;
                }
                $url = URL::fileURL($path);
                if ($this->shouldContinue) {
                    $this->isPostOrderDirectory = $url->hasDirectoryPath;
                    continue;
                }
                if ($keys = $this->keys) {
                    $values = $url->resourceValues(new Set($keys));
                    foreach ($values->allValues as $key => $value) {
                        $url->setTemporaryResourceValue($value, $key);
                    }
                }
                $this->currentURL = $url;
                yield $url;
                $this->shouldContinue = $this->isEnumeratingDirectoryPostOrder();
            }
        })();
    }
}
