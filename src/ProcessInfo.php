<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Random\RandomException;

/**
 * A collection of information about the current process.
 */
final class ProcessInfo extends ObjectClass
{
    private static ?ProcessInfo $processInfo = null;
    /** @var ArrayClass<string> Array of strings with the command-line arguments for the process. This array contains all the information passed in the argv array, including the executable name in the first element. */
    private(set) ArrayClass $arguments {
        get => $this->arguments ??= new ArrayClass($_SERVER["argv"] ?? []);
    }
    /** @var Dictionary<string> The variable names (keys) and their values in the environment from which the process was launched. */
    private(set) Dictionary $environment {
        get => $this->environment ??= Dictionary::dictionaryWithArray(parse_env_file(FileManager::default()->documentRootDirectory->appendingPathComponent(".env")->path));
    }
    /** @var string Global unique identifier for the process. */
    private(set) string $globallyUniqueString {
        /**
         * @throws RandomException
         */
        get => $this->globallyUniqueString ??= bin2hex(random_bytes(16));
    }
    /** @var int The identifier of the process (often called process ID). */
    private(set) int $processIdentifier {
        get => $this->processIdentifier ??= getmypid() ?: 0;
    }
    /** @var string The process name is used to register application defaults and is used in error messages. It does not uniquely identify the process. */
    public string $processName {
        get => $this->processName ??= process_name();
    }
    /** @var string Returns the account name of the current user. */
    private(set) string $userName {
        get => $this->userName ??= user_name();
    }
    /** @var string Returns the full name of the current user. */
    private(set) string $fullUserName {
        get => $this->fullUserName ??= full_user_name();
    }
    /** @var string The name of the host computer on which the process is executing. */
    private(set) string $hostName {
        get => $this->hostName ??= gethostname() ?: "localhost";
    }

    /**
     * Returns the process information agent for the process.
     */
    public static function processInfo(): ProcessInfo
    {
        return self::$processInfo ??= new ProcessInfo();
    }
}
