<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;

/**
 * The properties supported by file system resources.
 * @property-read bool|null $isDirectory True for directories.
 * @property-read string|null $fileResourceType Returns the file system object type.
 * @property-read int|null $fileSize Total file size in bytes.
 * @property-read bool|null $isExecutable True if this process (as determined by EUID) can execute a file resource or search a directory resource.
 * @property-read bool|null $isRegularFile True for regular files.
 * @property-read Dictionary $allValues A loosely-typed dictionary containing all keys and values.
 * @property-read Date|null $attributeModificationDate The time the resource's attributes were last modified.
 * @property-read Date|null $creationDate The date the resource was created.
 * @property-read bool|null $isAliasFile true if the resource is a Finder alias file or a symlink, false otherwise.
 * @property-read bool|null $isHidden True for resources normally not displayed to users.
 * @property-read bool|null $isReadable True if this process (as determined by EUID) can read the resource.
 * @property-read bool|null $isSymbolicLink True for symlinks.
 * @property-read bool|null $isWritable True if this process (as determined by EUID) can write to the resource.
 * @property-read string|null $name The resource name provided by the file system.
 * @property-read URL|null $parentDirectory The resource's parent directory, if any.
 * @property-read string|null $path The URL's path as a file system path.
 */
class URLResourceValues extends ObjectClass
{
    /**
     * @param Set<string> $keys
     * @param Dictionary $values
     */
    public function __construct(private readonly Set $keys, private readonly Dictionary $values)
    {
    }

    /** @internal */
    public function contains(#[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key): bool
    {
        return $this->keys->containsElement($key);
    }

    public function __get(string $name)
    {
        return match ($name) {
            "allValues" => $this->values,
            URLResourceKey::isApplicationKey, URLResourceKey::isDirectoryKey, URLResourceKey::parentDirectoryURLKey, URLResourceKey::fileResourceTypeKey, URLResourceKey::fileSizeKey, URLResourceKey::isExecutableKey, URLResourceKey::isRegularFileKey, URLResourceKey::attributeModificationDateKey, URLResourceKey::creationDateKey, URLResourceKey::isAliasFileKey, URLResourceKey::isHiddenKey, URLResourceKey::isReadableKey, URLResourceKey::isSymbolicLinkKey, URLResourceKey::isWritableKey, URLResourceKey::nameKey, URLResourceKey::pathKey => $this->values[$name],
            default => $this->valueForUndefinedKey($name)
        };
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name === URLResourceKey::isApplicationKey || $name === URLResourceKey::isDirectoryKey || $name === URLResourceKey::parentDirectoryURLKey || $name === URLResourceKey::fileResourceTypeKey || $name === URLResourceKey::fileSizeKey || $name === URLResourceKey::isExecutableKey || $name === URLResourceKey::isRegularFileKey || $name === URLResourceKey::attributeModificationDateKey || $name === URLResourceKey::creationDateKey || $name === URLResourceKey::isAliasFileKey || $name === URLResourceKey::isHiddenKey || $name === URLResourceKey::isReadableKey || $name === URLResourceKey::isSymbolicLinkKey || $name === URLResourceKey::isWritableKey || $name === URLResourceKey::nameKey || $name === URLResourceKey::pathKey) {
            $this->values->setValueForKey($value, $name);
            if ($value !== null) {
                $this->keys->append($name);
            } else {
                $this->keys->remove($name);
            }
        } else {
            $this->setValueForUndefinedKey($value, $name);
        }
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function __unset(string $name): void
    {
        unset($this->values[$name]);
    }
}
