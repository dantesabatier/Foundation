<?php
/**
 * Created by PhpStorm.
 * User: dante
 * Date: 07/07/20
 * Time: 10:55
 */

namespace Sabatier\Foundation;

/**
 * Class FileAttributeKey
 * Keys in dictionaries used to get and set file attributes.
 * @package Sabatier\Foundation
 */
class FileAttributeKey
{
    /** @var string The key in a file attribute dictionary whose value indicates whether the file is read-only. */
    const appendOnly = 'appendOnly';
    /** @var string The key in a file attribute dictionary whose value indicates whether the file is busy. */
    const busy = 'busy';
    /** @var string The key in a file attribute dictionary whose value indicates the file's creation date. */
    const creationDate = 'creationDate';
    /** @var string The key in a file attribute dictionary whose value indicates whether the file's extension is hidden. */
    const extensionHidden = 'extensionHidden';
    /** @var string The key in a file attribute dictionary whose value indicates the file's last modified date. */
    const modificationDate = 'modificationDate';
    /** @var string The key in a file attribute dictionary whose value indicates whether the file is mutable. */
    const immutable = 'immutable';
    /** @var string The key in a file attribute dictionary whose value indicates the file's group ID. */
    const groupOwnerAccountID = 'groupOwnerAccountID';
    /** @var string The key in a file attribute dictionary whose value indicates the group name of the file's owner. */
    const groupOwnerAccountName = 'groupOwnerAccountName';
    /** @var string The key in a file attribute dictionary whose value indicates the file's owner's account ID. */
    const ownerAccountID = 'ownerAccountID';
    /** @var string The key in a file attribute dictionary whose value indicates the name of the file's owner. */
    const ownerAccountName = 'ownerAccountName';
    /** @var string The key in a file attribute dictionary whose value indicates the file's Posix permissions. */
    const posixPermissions = 'posixPermissions';
    /** @var string The key in a file attribute dictionary whose value identifies the protection level for this file. */
    const protectionKey = 'protectionKey';
    /** @var string The key in a file attribute dictionary whose value indicates the file's size in bytes. */
    const size = 'size';
    /** @var string The key in a file attribute dictionary whose value indicates the file's type. The corresponding value is a string. See {@see FileAttributeType} for possible values.
     */
    const type = 'type';
}
