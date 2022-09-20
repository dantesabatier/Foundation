<?php

namespace Sabatier\Foundation;

use Closure;
use FilesystemIterator;
use Generator;
use JetBrains\PhpStorm\ExpectedValues;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Traversable;

/** @internal */
class URLDirectoryEnumerator extends DirectoryEnumerator
{
    private RecursiveIteratorIterator $iterator;
    private ?URL $current = null;
    private bool $shouldContinue = false;
    private bool $isPostOrderDirectory = false;

    public function __construct(private readonly URL $url, private readonly ?ArrayClass $keys = null, #[ExpectedValues(flagsFromClass: DirectoryEnumerationOptions::class)] private readonly int $options = DirectoryEnumerationOptions::skipsHiddenFiles, private readonly ?Closure $handler = null)
    {
        $directoryIterator = new RecursiveDirectoryIterator($this->url->path, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
        /** @psalm-suppress InvalidArgument */
        $filterIterator = new RecursiveCallbackFilterIterator($directoryIterator, fn(SplFileInfo $fileInfo): bool => !(($this->options & DirectoryEnumerationOptions::skipsSubdirectoryDescendants || $this->options & DirectoryEnumerationOptions::skipsPackageDescendants) && $fileInfo->isDir() && !$fileInfo->getExtension()) && !(($this->options & DirectoryEnumerationOptions::skipsHiddenFiles) && is_hidden($fileInfo->getPathname())));
        $this->iterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::SELF_FIRST);
    }

    public function directoryAttributes(): ?Dictionary
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return FileManager::default()->attributesOfItem($this->url->path);
    }

    public function fileAttributes(): ?Dictionary
    {
        if ($current = $this->current) {
            /** @noinspection PhpUnhandledExceptionInspection */
            return FileManager::default()->attributesOfItem($current->path);
        }
        return null;
    }

    public function level(): int
    {
        return $this->iterator->getDepth();
    }

    public function skipDescendents(): void
    {
        $this->skipDescendants();
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
            /** @var SplFileInfo $info */
            foreach ($this->iterator as $info) {
                $url = URL::fileURL($info->getPathname());
                if (($handler = $this->handler) && !$handler($url)) {
                    break;
                }
                if ($this->shouldContinue) {
                    $this->isPostOrderDirectory = $info->isDir();
                    continue;
                }
                if ($keys = $this->keys) {
                    $values = $url->resourceValues(new Set($keys));
                    foreach ($values->allValues as $key => $value) {
                        $url->setTemporaryResourceValue($value, $key);
                    }
                }
                $this->current = $url;
                yield $url;
                $this->shouldContinue = $this->isEnumeratingDirectoryPostOrder();
            }
        })();
    }
}
