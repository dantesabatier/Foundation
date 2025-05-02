<?php

namespace Sabatier\Foundation;

use IteratorAggregate;

/**
 * An object that enumerates the contents of a directory.
 *
 * You obtain a directory enumerator using FileManager's {@see FileManager::enumerator()} method. The enumeration provides the pathnames of all files and directories contained within that directory. These pathnames are relative to the directory. An enumeration is recursive, including the files of all subdirectories, and crosses device boundaries. An enumeration does not resolve symbolic links or attempt to traverse symbolic links that point to directories.
 * @template T
 * @implements IteratorAggregate<T>
 */
abstract class DirectoryEnumerator implements IteratorAggregate
{
    /**
     * A dictionary with the attributes of the directory at which enumeration started.
     */
    public function directoryAttributes(): ?Dictionary
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    /**
     * A dictionary with the attributes of the most recently returned file or subdirectory (as referenced by the pathname).
     */
    public function fileAttributes(): ?Dictionary
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    /**
     * The number of levels deep the current object is in the directory hierarchy being enumerated.
     */
    public function level(): int
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    /**
     * Causes the receiver to skip recursion into the most recently obtained subdirectory.
     */
    public function skipDescendants(): void
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    public function isEnumeratingDirectoryPostOrder(): bool
    {
        request_concrete_implementation($this, __FUNCTION__);
    }
}
