<?php

namespace Sabatier\Foundation;

use Closure;
use Exception;
use FilesystemIterator;
use Generator;
use JetBrains\PhpStorm\ExpectedValues;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Traversable;
use UnexpectedValueException;

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
        $directoryIterator = new RecursiveDirectoryIterator($this->url->path, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
        /** @psalm-suppress InvalidArgument */
        $filterIterator = new RecursiveCallbackFilterIterator($directoryIterator, fn(SplFileInfo $fileInfo): bool => !(($this->options & DirectoryEnumerationOptions::skipsSubdirectoryDescendants || $this->options & DirectoryEnumerationOptions::skipsPackageDescendants) && $fileInfo->isDir() && !$fileInfo->getExtension()) && !(($this->options & DirectoryEnumerationOptions::skipsHiddenFiles) && is_hidden($fileInfo->getPathname())));
        $this->iterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::SELF_FIRST);
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
            $handle = function (Exception $exception): bool {
                if ($errorHandler = $this->errorHandler) {
                    $code = NotFound;
                    if ($exception instanceof UnexpectedValueException) {
                        $code = FileReadNoPermissionError;
                    }
                    return $errorHandler($this->currentURL, new Error(CocoaErrorDomain, $code, new Dictionary([URLErrorKey => $this->currentURL])));
                }
                return false;
            };
            try {
                /** @var SplFileInfo $fileInfo */
                foreach ($this->iterator as $fileInfo) {
                    try {
                        $url = URL::fileURL($fileInfo->getPathname());
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
                    } catch (Exception $exception) {
                        if (!$handle($exception)) {
                            break;
                        }
                    }
                }
            } catch (Exception $exception) {
                $handle($exception);
            }
        })();
    }
}
