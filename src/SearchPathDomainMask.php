<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 05/07/20
 * Time: 11:37
 */

namespace Sabatier\Foundation;

/**
 * Domain constants specifying base locations to use when you search for significant directories.
 *
 * These constants are used by the {@see FileManager::urls()} and {@see FileManager::url()} methods of FileManager.
 */
final class SearchPathDomainMask
{
    /** @var int The user's home directory—the place to install user's personal items (~). */
    final const int user = 1;
    /** @var int The place to install items available to everyone on this machine. */
    final const int local = 2;
    /** @var int A directory for system files. */
    final const int system = 4;
    /** @var int All domains. */
    final const int all = SearchPathDomainMask::user | SearchPathDomainMask::local | SearchPathDomainMask::system;
}
