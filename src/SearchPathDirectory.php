<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 05/07/20
 * Time: 11:30
 */

namespace Sabatier\Foundation;

/**
 * The location of significant directories.
 *
 * These constants are used by the {@see FileManager::urls()} and {@see FileManager::url()} methods of FileManager.
 */
enum SearchPathDirectory: int
{
    /** Supported applications (/Applications). */
    case applicationsDirectory = 1;
    /** Various user-visible documentation, support, and configuration files (/Library). */
    case libraryDirectory = 5;
    /** Document directory. */
    case documentsDirectory = 9;
    /** The user's desktop directory. */
    case desktopDirectory = 12;
    /** Discardable cache files (Library/Caches). */
    case cachesDirectory = 13;
    /** Application support files (Library/Application Support). */
    case applicationSupportDirectory = 14;
    /** The user's downloads directory. */
    case downloadsDirectory = 15;
    /** The user's Movies directory (~/Movies). */
    case moviesDirectory = 17;
    /** The user's Music directory (~/Music). */
    case musicDirectory = 18;
    /** The user's Pictures directory (~/Pictures). */
    case picturesDirectory = 19;
    /** The user's Public sharing directory (~/Public). */
    case sharedPublicDirectory = 21;
    /** The constant used to create a temporary directory. */
    case itemReplacementDirectory = 99;
    /** The trash directory. */
    case trashDirectory = 102;
}
