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
/** @var int A properly formed URL couldn't be handled by the framework. */
const URLErrorUnsupportedURL = -1002;
/** @var int The host name for a URL couldn't be resolved. */
const URLErrorCannotFindHost = -1003;
/** @var int A malformed URL prevented a URL request from being initiated. */
const URLErrorBadURL = -1000;
/** @var int A client or server connection was severed in the middle of an in-progress load. */
const URLErrorNetworkConnectionLost = -1005;
/** @var int The URL Loading System received bad data from the server. */
const URLErrorBadServerResponse = -1011;
/** @var int The URL Loading System encountered an error that it can’t interpret. */
const URLErrorUnknown = -1;
/** @var int An asynchronous operation timed out. */
const URLErrorTimedOut = -1001;
/** @var int A redirect loop was detected or the threshold for number of allowable redirects was exceeded (currently 16). */
const URLErrorHTTPTooManyRedirects = -1007;
