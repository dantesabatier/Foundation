<?php

namespace Sabatier\Foundation;

/** @var string A filesystem operation was attempted on a non-existent file. */
const FileNoSuchFileError = 4;
/** @var string Could not read because of a permission problem. */
const FileReadNoPermissionError = 257;
/** @var string Could not write because of a permission problem. */
const FileWriteNoPermissionError = 513;
/** @var string Could not perform an operation because the destination file already exists. */
const FileWriteFileExistsError = 516;
/** @var string A formatter couldn't generate a string for an object, or parse a string into an object. */
const FormattingError = 2048;
/** @var string The user canceled the operation (for example, by pressing Command-period) */
const UserCancelledError = 3072;
/** @var string A key-value coding validation error. */
const KeyValueValidationError = 1024;
