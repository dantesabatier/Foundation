<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Closure;
use Exception;
use FilesystemIterator;
use Generator;
use JetBrains\PhpStorm\ExpectedValues;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Traversable;

/**
 * @extends DirectoryEnumerator<URL>
 * @internal
 */
final class URLDirectoryEnumerator extends DirectoryEnumerator
{
    private readonly RecursiveIteratorIterator $iterator;
    private ?URL $currentURL = null;
    private bool $shouldContinue = false;
    #[Override]
    public ?Dictionary $directoryAttributes {
        get {
            try {
                return FileManager::default()->attributesOfItem($this->url->path);
            } catch (Exception) {
                return null;
            }
        }
    }
    #[Override]
    public ?Dictionary $fileAttributes {
        get {
            if (!($currentURL = $this->currentURL)) {
                return null;
            }
            try {
                return FileManager::default()->attributesOfItem($currentURL->path);
            } catch (Exception) {
                return null;
            }
        }
    }
    #[Override]
    public int $level {
        get => $this->iterator->getDepth();
    }
    #[Override]
    private(set) bool $isEnumeratingDirectoryPostOrder = false;

    public function __construct(private readonly URL $url, private readonly ?ArrayClass $keys = null, #[ExpectedValues(flagsFromClass: DirectoryEnumerationOptions::class)] private readonly int $options = DirectoryEnumerationOptions::skipsHiddenFiles, private readonly ?Closure $errorHandler = null)
    {
        $this->iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->url->path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS), RecursiveIteratorIterator::CHILD_FIRST);
    }

    #[Override]
    public function skipDescendants(): void
    {
        $this->shouldContinue = true;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return (function (): Generator {
            /** @var Set<string>|null $keys */
            $keys = $this->keys ? new Set($this->keys) : null;
            foreach ($this->iterator as $path) {
                $path = (string)$path;
                $url = URL::fileURL($path);
                if (($this->options & DirectoryEnumerationOptions::skipsSubdirectoryDescendants || $this->options & DirectoryEnumerationOptions::skipsPackageDescendants) && !$this->url->isEqual($url->deletingLastPathComponent())) {
                    continue;
                }
                if ($this->options & DirectoryEnumerationOptions::skipsHiddenFiles && is_hidden($path)) {
                    continue;
                }
                if ($this->shouldContinue) {
                    $this->isEnumeratingDirectoryPostOrder = $url->hasDirectoryPath;
                    continue;
                }
                if ($keys !== null) {
                    try {
                        $values = $url->resourceValues($keys);
                        foreach ($values->allValues as $key => $value) {
                            $url->setTemporaryResourceValue($value, $key);
                        }
                    } catch (Exception) {
                        if (($errorHandler = $this->errorHandler) && !$errorHandler(new Error(CocoaErrorDomain, FileReadNoPermissionError), $url)) {
                            break;
                        }
                    }
                }
                $this->currentURL = $url;
                yield $url;
                $this->shouldContinue = $this->isEnumeratingDirectoryPostOrder;
            }
        })();
    }
}
