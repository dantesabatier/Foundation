<?php

namespace Sabatier\Foundation;

/**
 * Values representing a file's type attribute {@see FileAttributeKey::type}.
 */
final class FileAttributeType
{
    /** @var string A FIFO special file (a named pipe). */
    final const string fifo = "fifo";
    /** @var string A block special file. */
    final const string blockSpecial = "block";
    /** @var string A character special file. */
    final const string characterSpecial = "char";
    /** @var string A directory. */
    final const string directory = "dir";
    /** @var string A regular file. */
    final const string regular = "file";
    /** @var string A socket. */
    final const string socket = "socket";
    /** @var string A symbolic link. */
    final const string symbolicLink = "link";
    /** @var string A file whose type is unknown. */
    final const string unknown = "unknown";
}
