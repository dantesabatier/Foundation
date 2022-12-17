<?php

namespace Sabatier\Foundation;

/**
 * Values representing a file's type attribute {@see FileAttributeKey::type}.
 */
class FileAttributeType
{
    /** @var string A FIFO special file (a named pipe). */
    final const fifo = "fifo";
    /** @var string A block special file. */
    final const blockSpecial = "block";
    /** @var string A character special file. */
    final const characterSpecial = "char";
    /** @var string A directory. */
    final const directory = "dir";
    /** @var string A regular file. */
    final const regular = "file";
    /** @var string A socket. */
    final const socket = "socket";
    /** @var string A symbolic link. */
    final const symbolicLink = "link";
    /** @var string A file whose type is unknown. */
    final const unknown = "unknown";
}
