<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * Options for enumerating the contents of directories.
 *
 * These options are used with the {@see FileManager::contentsOfDirectory()} method.
 */
final class DirectoryEnumerationOptions
{
    /** @var int An option to perform a shallow enumeration that doesn't descend into directories. */
    final const int skipsSubdirectoryDescendants = 1 << 0;
    /** @var int An option to treat packages like files and not descend into their contents. */
    final const int skipsPackageDescendants = 1 << 1;
    /** @var int An option to skip hidden files. */
    final const int skipsHiddenFiles = 1 << 2;
}
