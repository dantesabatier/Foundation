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
    final const string appendOnly = "appendOnly";
    /** @var string The key in a file attribute dictionary whose value indicates whether the file is busy. */
    final const string busy = "busy";
    /** @var string The key in a file attribute dictionary whose value indicates the file's creation date. */
    final const string creationDate = "creationDate";
    /** @var string The key in a file attribute dictionary whose value indicates whether the file's extension is hidden. */
    final const string extensionHidden = "extensionHidden";
    /** @var string The key in a file attribute dictionary whose value indicates the file's last modified date. */
    final const string modificationDate = "modificationDate";
    /** @var string The key in a file attribute dictionary whose value indicates whether the file is mutable. */
    final const string immutable = "immutable";
    /** @var string The key in a file attribute dictionary whose value indicates the file's group ID. */
    final const string groupOwnerAccountID = "groupOwnerAccountID";
    /** @var string The key in a file attribute dictionary whose value indicates the group name of the file's owner. */
    final const string groupOwnerAccountName = "groupOwnerAccountName";
    /** @var string The key in a file attribute dictionary whose value indicates the file's owner's account ID. */
    final const string ownerAccountID = "ownerAccountID";
    /** @var string The key in a file attribute dictionary whose value indicates the name of the file's owner. */
    final const string ownerAccountName = "ownerAccountName";
    /** @var string The key in a file attribute dictionary whose value indicates the file's Posix permissions. */
    final const string posixPermissions = "posixPermissions";
    /** @var string The key in a file attribute dictionary whose value identifies the protection level for this file. */
    final const string protectionKey = "protectionKey";
    /** @var string The key in a file attribute dictionary whose value indicates the file's size in bytes. */
    final const string size = "size";
    /** @var string The key in a file attribute dictionary whose value indicates the file's type. The corresponding value is a string. See {@see FileAttributeType} for possible values.
     */
    final const string type = "type";
}
