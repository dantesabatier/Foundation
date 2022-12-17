<?php

namespace Sabatier\Foundation;

/**
 * Keys that apply to file system URLs.
 *
 * To request information using one of these keys, pass it to the $keys parameter of the {@see URL::resourceValues()} instance method.
 */
class URLResourceKey
{
    final const isApplicationKey = "isApplication";
    /** @var string Key for determining whether the resource is a directory (read-only). */
    final const isDirectoryKey = "isDirectory";
    /** @var string The parent directory of the resource, returned as a URL object, or nil if the resource is the root directory of its volume (read-only). */
    final const parentDirectoryURLKey = "parentDirectory";
    /** @var string The resource's object type, returned as a string (read-only). */
    final const fileResourceTypeKey = "fileResourceType";
    /** @var string Key for the file's size in bytes (read-only). */
    final const fileSizeKey = "fileSize";
    /** @var string Key for determining whether the file is an alias, returned as a Boolean Number object (read-only). */
    final const isAliasFileKey = "isAliasFile";
    /** @var string Key for determining whether the resource is a regular file, as opposed to a directory or a symbolic link (read-only). */
    final const isRegularFileKey = "isRegularFile";
    /** @var string The time at which the resource's attributes were most recently modified, returned as a Date object if the volume supports attribute modification dates, or nil if attribute modification dates are unsupported (read-only). */
    final const attributeModificationDateKey = "attributeModificationDate";
    /** @var string The time at which the resource was created. */
    final const creationDateKey = "creationDate";
    /** @var string Key for determining whether the current process (as determined by the EUID) can execute the resource (if it is a file) or search the resource (if it is a directory) (read-only). */
    final const isExecutableKey = "isExecutable";
    /** @var string Key for determining whether the resource is normally not displayed to users, returned as a Boolean Number object (read-write) */
    final const isHiddenKey = "isHidden";
    /** @var string Key for determining whether the current process (as determined by the EUID) can read the resource, returned as a Boolean Number object (read-only). */
    final const isReadableKey = "isReadable";
    /** @var string Key for determining whether the resource is a symbolic link, returned as a Boolean Number object (read-only). */
    final const isSymbolicLinkKey = "isSymbolicLink";
    /** @var string Key for determining whether the current process (as determined by the EUID) can write to the resource, returned as a Boolean Number object (read-only). */
    final const isWritableKey = "isWritable";
    /** @var string The resource's name in the file system, returned as a string (read-write). */
    final const nameKey = "name";
    /** @var string The file system path for the URL, returned as a string (read-only). */
    final const pathKey = "path";
}
