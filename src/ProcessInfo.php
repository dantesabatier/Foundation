<?php

namespace Sabatier\Foundation;

use Exception;

/**
 * A collection of information about the current process.
 */
class ProcessInfo extends ObjectClass
{
    private static ?ProcessInfo $processInfo = null;
    /** @var ArrayClass<string> Array of strings with the command-line arguments for the process. This array contains all the information passed in the argv array, including the executable name in the first element. */
    public ArrayClass $arguments {
        get => $this->associatedValues[__PROPERTY__] ??= new ArrayClass($_SERVER["argv"] ?? []);
    }
    /** @var Dictionary<string> The variable names (keys) and their values in the environment from which the process was launched. */
    public Dictionary $environment {
        get => $this->associatedValues[__PROPERTY__] ??= $this->environment();
    }
    /** @var string Global unique identifier for the process. */
    public string $globallyUniqueString {
        get => $this->associatedValues[__PROPERTY__] ??= md5((string)$this->processIdentifier);
    }
    /** @var int The identifier of the process (often called process ID). */
    public int $processIdentifier {
        get => getmypid();
    }
    /** @var string The process name is used to register application defaults and is used in error messages. It does not uniquely identify the process. */
    public string $processName {
        get => $this->associatedValues[__PROPERTY__] ??= $this->processName();
        set => $this->associatedValues[__PROPERTY__] = $value;
    }
    /** @var string Returns the account name of the current user. */
    public string $userName {
        get => get_current_user();
    }
    /** @var string Returns the full name of the current user. */
    public string $fullUserName {
        get => get_current_user();
    }
    /** @var string The name of the host computer on which the process is executing. */
    public string $hostName {
        get => gethostname();
    }

    /**
     * @throws Exception
     */
    private function environment(): Dictionary
    {
        /** @var Dictionary<string> $environment */
        $environment = new Dictionary();
        $fileManager = FileManager::default();
        $url = $fileManager->documentRootDirectory->appendingPathComponent(".env");
        $path = $url->path;
        if ($fileManager->fileExists($path) && ($string = $fileManager->contents($path))) {
            $scanner = new Scanner($string);
            $scanner->charactersToBeSkipped = PHP_EOL;
            while ($scanner->scanUpCharacters(PHP_EOL, $line) && $line) {
                /** @psalm-suppress RedundantCast */
                $components = explode("=", (string)$line, 2);
                if (count($components) === 2) {
                    [$key, $value] = $components;
                    $environment[trim($key)] = trim($value, "\"' ");
                    $scanner->scanLocation += 1;
                }
            }
        }
        return $environment;
    }

    private function processName(): string
    {
        $processName = "Unknown";
        /** @psalm-suppress RedundantCondition */
        if (RUNNING_FROM_CLI) {
            $processTitle = cli_get_process_title();
            if ($processTitle !== null) {
                $processName = $processTitle;
            }
        }
        return $processName;
    }

    /**
     * Returns the process information agent for the process.
     */
    public static function processInfo(): ProcessInfo
    {
        self::$processInfo ??= new ProcessInfo();
        return self::$processInfo;
    }
}
