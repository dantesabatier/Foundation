<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * Represents the type of change in a collection difference operation.
 *
 * This enum defines the possible types of changes that can occur when comparing two collections and determining their differences.
 */
enum CollectionDifferenceChangeType: int
{
    /** An insertion. */
    case insert = 0;
    /** A removal. */
    case remove = 1;
    /** A move. */
    case move = 2;
}
