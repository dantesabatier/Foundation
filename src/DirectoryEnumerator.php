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
    /** @var Dictionary|null A dictionary with the attributes of the directory at which enumeration started. */
    abstract public ?Dictionary $directoryAttributes {
        get;
    }
    /** @var Dictionary|null A dictionary with the attributes of the most recently returned file or subdirectory (as referenced by the pathname). */
    abstract ?Dictionary $fileAttributes {
        get;
    }
    /** @var int The number of levels deep the current object is in the directory hierarchy being enumerated. */
    abstract public int $level {
        get;
    }
    abstract public bool $isEnumeratingDirectoryPostOrder {
        get;
    }

    /**
     * Causes the receiver to skip recursion into the most recently obtained subdirectory.
     */
    public function skipDescendants(): void
    {
        request_concrete_implementation($this, __FUNCTION__);
    }
}
