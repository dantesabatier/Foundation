<?php

namespace Sabatier\Foundation;

/**
 * A hint to URL file APIs for handling paths that may reference directories.
 */
enum URLDirectoryHint: int
{
    /** A hint that specifies that a given path is a directory. */
    case isDirectory = 0;
    /** A hint that specifies that a given path isn’t a directory. */
    case notDirectory = 1;
    /** A hint that directs a URL call to consult the file system to determine whether the path references a directory. */
    case checkFileSystem = 2;
    /** A hint that directs a URL call to infer whether a path references a directory based on whether it has a trailing slash. */
    case inferFromPath = 3;
}
