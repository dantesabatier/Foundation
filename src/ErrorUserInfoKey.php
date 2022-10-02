<?php

namespace Sabatier\Foundation;

/** @var string The corresponding value is a localized string representation of the error that, if present, will be returned by {@see Error::localizedDescription}. */
const LocalizedDescriptionKey = 'LocalizedDescriptionKey';
/** @var string The corresponding value is an array containing the localized titles of buttons appropriate for displaying in an alert panel. The first string is the title of the right-most and default button, the second the one to the left, and so on. The recovery options should be appropriate for the recovery suggestion returned by {@see Error::localizedRecoverySuggestion}. */
const LocalizedRecoveryOptionsErrorKey = 'LocalizedRecoveryOptionsErrorKey';
/** @var string The corresponding value is a string containing the localized recovery suggestion for the error. */
const LocalizedRecoverySuggestionErrorKey = 'LocalizedRecoverySuggestionErrorKey';
/** @var string The corresponding value is a localized string representation containing the reason for the failure that, if present, will be returned by {@see Error::localizedFailureReason}. */
const LocalizedFailureReasonErrorKey = 'LocalizedFailureReasonErrorKey';
/** @var string The corresponding value is an object that conforms to the ErrorRecoveryAttempting informal protocol. The recovery attempter must be an object that can correctly interpret an index into the array returned by {@see Error::recoveryAttempter}. */
const RecoveryAttempterErrorKey = 'RecoveryAttempterErrorKey';
/** @var string The corresponding value is a URL object. */
const URLErrorKey = 'URLErrorKey';
/** @var string Contains the file path of the error. */
const FilePathErrorKey = 'FilePathErrorKey';
/** The corresponding value is an error that was encountered in an underlying implementation and caused the error that the receiver represents to occur. */
const UnderlyingErrorKey = 'UnderlyingErrorKey';
const DebugDescriptionErrorKey = 'DebugDescriptionErrorKey';
const MultipleUnderlyingErrorsKey = 'MultipleUnderlyingErrorsKey';
