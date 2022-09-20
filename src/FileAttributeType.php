<?php

namespace Sabatier\Foundation;

/**
 * Class FileAttributeType
 * Values representing a file's type attribute {@see FileAttributeKey::type}.
 * @package Sabatier\Foundation
 */
class FileAttributeType
{
    /** @var string A FIFO special file (a named pipe). */
    const fifo = 'fifo';
    /** @var string A block special file. */
    const blockSpecial = 'block';
    /** @var string A character special file. */
    const characterSpecial = 'char';
    /** @var string A directory. */
    const directory = 'dir';
    /** @var string A regular file. */
    const regular = 'file';
    /** @var string A socket. */
    const socket = 'socket';
    /** @var string A symbolic link. */
    const symbolicLink = 'link';
    /** @var string A file whose type is unknown. */
    const unknown = 'unknown';
}
