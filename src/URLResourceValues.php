<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;

/**
 * The properties supported by file system resources.
 */
final class URLResourceValues extends ObjectClass
{
    /** @var bool|null True for directories. */
    public ?bool $isDirectory {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var string|null Returns the file system object type. */
    public ?string $fileResourceType {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var int|null Total file size in bytes. */
    public ?int $fileSize {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var bool|null True if this process (as determined by EUID) can execute a file resource or search a directory resource. */
    public ?bool $isExecutable {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var bool|null True for regular files. */
    public ?bool $isRegularFile {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var Date|null The time the resource's attributes were last modified. */
    public ?Date $attributeModificationDate {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var Date|null The date the resource was created. */
    public ?Date $creationDate {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var bool|null true if the resource is a Finder alias file or a symlink, false otherwise. */
    public ?bool $isAliasFile {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var bool|null True for resources normally not displayed to users. */
    public ?bool $isHidden {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var bool|null True if this process (as determined by EUID) can read the resource. */
    public ?bool $isReadable {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var bool|null True for symlinks. */
    public ?bool $isSymbolicLink {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var bool|null True if this process (as determined by EUID) can write to the resource. */
    public ?bool $isWritable {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var string|null The resource name provided by the file system. */
    public ?string $name {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var URL|null The resource's parent directory, if any. */
    public ?URL $parentDirectory {
        get => $this->allValues[__PROPERTY__];
    }
    /** @var string|null The URL's path as a file system path. */
    public ?string $path {
        get => $this->allValues[__PROPERTY__];
    }

    /**
     * @param Set<string> $keys
     * @param Dictionary $allValues A loosely typed dictionary containing all keys and values.
     */
    public function __construct(public readonly Set $keys, private(set) Dictionary $allValues)
    {
    }

    /** @internal */
    public function contains(#[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key): bool
    {
        return $this->keys->containsElement($key);
    }

    public function __isset(string $name): bool
    {
        return isset($this->allValues[$name]);
    }

    public function __unset(string $name): void
    {
        unset($this->allValues[$name]);
    }
}
