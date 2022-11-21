<?php

namespace Sabatier\Foundation;

/**
 * Options for enumerating the contents of directories.
 *
 * These options are used with the {@see FileManager::contentsOfDirectory()} method.
 */
class DirectoryEnumerationOptions
{
    /** @var int An option to perform a shallow enumeration that doesn't descend into directories. */
    final const skipsSubdirectoryDescendants = 1 << 0;
    /** @var int An option to treat packages like files and not descend into their contents. */
    final const skipsPackageDescendants = 1 << 1;
    /** @var int An option to skip hidden files. */
    final const skipsHiddenFiles = 1 << 2;
}
