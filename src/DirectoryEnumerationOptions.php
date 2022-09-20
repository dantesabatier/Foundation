<?php

namespace Sabatier\Foundation;

/**
 * Class DirectoryEnumerationOptions
 * Options for enumerating the contents of directories.
 * These options are used with the {@see FileManager::contentsOfDirectory()} method.
 * @package Sabatier\Foundation
 */
class DirectoryEnumerationOptions
{
    /** @var int An option to perform a shallow enumeration that doesn't descend into directories. */
    const skipsSubdirectoryDescendants = 1 << 0;
    /** @var int An option to treat packages like files and not descend into their contents. */
    const skipsPackageDescendants = 1 << 1;
    /** @var int An option to skip hidden files. */
    const skipsHiddenFiles = 1 << 2;
}
