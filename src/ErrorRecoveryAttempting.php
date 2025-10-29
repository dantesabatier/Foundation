<?php

namespace Sabatier\Foundation;

/**
 * A set of methods that provide options to recover from an error.
 */
interface ErrorRecoveryAttempting
{
    /**
     * Implemented to attempt a recovery from an error noted in an application-modal dialog.
     *
     * Invoked when an error alert is presented to the user in an application-modal dialog, and the user has selected an error recovery option specified by error.
     * @param Error $error An Error object that describes the error, including error recovery options.
     * @param int $optionIndex The index of the user-selected recovery option in error's localized recovery array.
     * @return bool true if the error recovery was completed successfully, false otherwise.
     */
    public function attemptRecovery(Error $error, int $optionIndex): bool;
}
