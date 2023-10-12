<?php

namespace Sabatier\Foundation;

/**
 * An object that represents the kind of progress.
 *
 * When tracking file operations with the progress kind set to file, provide a value for the fileOperationKindKey in the user info dictionary.
 */
class ProgressKind
{
    /** @var string The value that indicates that the progress is tracking a file operation. */
    final const file = "file";
}