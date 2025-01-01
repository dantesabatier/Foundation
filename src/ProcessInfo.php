<?php

namespace Sabatier\Foundation;

/**
 * A collection of information about the current process.
 */
class ProcessInfo extends ObjectClass
{
    private static ?ProcessInfo $processInfo = null;
    /** @var ArrayClass<string> Array of strings with the command-line arguments for the process. This array contains all the information passed in the argv array, including the executable name in the first element. */
    private(set) ArrayClass $arguments {
        get => $this->arguments ??= new ArrayClass($_SERVER["argv"] ?? []);
    }
    /** @var Dictionary<string> The variable names (keys) and their values in the environment from which the process was launched. */
    private(set) Dictionary $environment {
        get {
            if (!isset($this->environment)) {
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
                        $components = explode("=", $line, 2);
                        if (count($components) === 2) {
                            [$key, $value] = $components;
                            $environment[trim($key)] = trim($value, "\"' ");
                            $scanner->scanLocation += 1;
                        }
                    }
                }
                $this->environment = $environment;
            }
            return $this->environment;
        }
    }
    /** @var string Global unique identifier for the process. */
    private(set) string $globallyUniqueString {
        get => $this->globallyUniqueString ??= md5((string)$this->processIdentifier);
    }
    /** @var int The identifier of the process (often called process ID). */
    private(set) int $processIdentifier {
        get => $this->processIdentifier ??= getmypid();
    }
    /** @var string The process name is used to register application defaults and is used in error messages. It does not uniquely identify the process. */
    public string $processName {
        get {
            if (!isset($this->processName)) {
                $processName = "Unknown";
                /** @psalm-suppress RedundantCondition */
                if (RUNNING_FROM_CLI) {
                    $processTitle = cli_get_process_title();
                    if ($processTitle !== null) {
                        $processName = $processTitle;
                    }
                }
                $this->processName = $processName;
            }
            return $this->processName;
        }
    }
    /** @var string Returns the account name of the current user. */
    private(set) string $userName {
        get => $this->userName ??= get_current_user();
    }
    /** @var string Returns the full name of the current user. */
    private(set) string $fullUserName {
        get => $this->fullUserName ??= get_current_user();
    }
    /** @var string The name of the host computer on which the process is executing. */
    private(set) string $hostName {
        get => $this->hostName ??= gethostname();
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
