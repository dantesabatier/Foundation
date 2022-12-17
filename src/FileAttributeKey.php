<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 07/07/20
 * Time: 10:55
 */

namespace Sabatier\Foundation;

/**
 * Keys in dictionaries used to get and set file attributes.
 */
class FileAttributeKey
{
    /** @var string The key in a file attribute dictionary whose value indicates whether the file is read-only. */
    final const appendOnly = "appendOnly";
    /** @var string The key in a file attribute dictionary whose value indicates whether the file is busy. */
    final const busy = "busy";
    /** @var string The key in a file attribute dictionary whose value indicates the file's creation date. */
    final const creationDate = "creationDate";
    /** @var string The key in a file attribute dictionary whose value indicates whether the file's extension is hidden. */
    final const extensionHidden = "extensionHidden";
    /** @var string The key in a file attribute dictionary whose value indicates the file's last modified date. */
    final const modificationDate = "modificationDate";
    /** @var string The key in a file attribute dictionary whose value indicates whether the file is mutable. */
    final const immutable = "immutable";
    /** @var string The key in a file attribute dictionary whose value indicates the file's group ID. */
    final const groupOwnerAccountID = "groupOwnerAccountID";
    /** @var string The key in a file attribute dictionary whose value indicates the group name of the file's owner. */
    final const groupOwnerAccountName = "groupOwnerAccountName";
    /** @var string The key in a file attribute dictionary whose value indicates the file's owner's account ID. */
    final const ownerAccountID = "ownerAccountID";
    /** @var string The key in a file attribute dictionary whose value indicates the name of the file's owner. */
    final const ownerAccountName = "ownerAccountName";
    /** @var string The key in a file attribute dictionary whose value indicates the file's Posix permissions. */
    final const posixPermissions = "posixPermissions";
    /** @var string The key in a file attribute dictionary whose value identifies the protection level for this file. */
    final const protectionKey = "protectionKey";
    /** @var string The key in a file attribute dictionary whose value indicates the file's size in bytes. */
    final const size = "size";
    /** @var string The key in a file attribute dictionary whose value indicates the file's type. The corresponding value is a string. See {@see FileAttributeType} for possible values.
     */
    final const type = "type";
}
