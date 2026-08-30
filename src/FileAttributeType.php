<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * Values representing a file's type attribute {@see FileAttributeKey::type}.
 */
final class FileAttributeType
{
    /** @var string A FIFO special file (a named pipe). */
    const string fifo = "fifo";
    /** @var string A block special file. */
    const string blockSpecial = "block";
    /** @var string A character special file. */
    const string characterSpecial = "char";
    /** @var string A directory. */
    const string directory = "dir";
    /** @var string A regular file. */
    const string regular = "file";
    /** @var string A socket. */
    const string socket = "socket";
    /** @var string A symbolic link. */
    const string symbolicLink = "link";
    /** @var string A file whose type is unknown. */
    const string unknown = "unknown";
}
