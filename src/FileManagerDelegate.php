<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * The interface a file manager's delegate uses to intervene during operations or if an error occurs.
 */
interface FileManagerDelegate
{
    /**
     * Asks the delegate if the file manager should move the specified item to the new URL.
     * @param FileManager $fileManager The file manager object that is attempting to move the file or directory.
     * @param URL $srcURL The URL of the file or directory that the file manager wants to move.
     * @param URL $dstURL The URL specifying the new location for the file or directory.
     * @return bool true if the item should be moved or false if it should not be moved.
     * If you do not implement this method, the file manager assumes a response of true.
     * This method is called only once for the item being moved, regardless of whether the item is a file, directory, or symbolic link.
     */
    public function fileManagerShouldMoveItemAtURL(FileManager $fileManager, URL $srcURL, URL $dstURL): bool;

    /**
     * Asks the delegate if the file manager should copy the specified item to the new URL.
     * @param FileManager $fileManager The file manager object that is attempting to copy the file or directory.
     * @param URL $srcURL The URL of the file or directory that the file manager wants to copy.
     * @param URL $dstURL The URL specifying the location for the copied file or directory.
     * @return bool true if the item should be copied or false if the file manager should stop copying items associated with the current operation.
     * If you do not implement this method, the file manager assumes a response of true.
     * This method is called once for each item that needs to be copied.
     * Thus, for a directory, this method is called once for the directory and once for each item in the directory.
     */
    public function fileManagerShouldCopyItemAtURL(FileManager $fileManager, URL $srcURL, URL $dstURL): bool;

    /**
     * Asks the delegate whether the item at the specified URL should be deleted.
     * @param FileManager $fileManager The file manager object that is attempting to remove the file or directory.
     * @param URL $url The URL indicating the file or directory that the file manager is attempting to delete.
     * @return bool true if the specified item should be removed or false if it should not be removed.
     * Removed items are deleted immediately and not placed in the Trash.
     * If the specified item is a directory, returning false prevents both the directory and its children from being deleted.
     */
    public function fileManagerShouldRemoveItemAtURL(FileManager $fileManager, URL $url): bool;

    /**
     * Asks the delegate if a hard link should be created between the items at the two URLs.
     * @param FileManager $fileManager The file manager object that is attempting to create the link.
     * @param URL $srcURL The URL identifying the new hard link to be created.
     * @param URL $dstURL The URL identifying the destination of the link.
     * @return bool true if the link should be created or false if it should not be created.
     * If the item specified by destURL is a directory, returning false prevents links from being created to both the directory and its children.
     */
    public function fileManagerShouldLinkItemAtURL(FileManager $fileManager, URL $srcURL, URL $dstURL): bool;
}
