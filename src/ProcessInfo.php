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
    public readonly ArrayClass $arguments;
    /** @var Dictionary<string> The variable names (keys) and their values in the environment from which the process was launched. */
    public readonly Dictionary $environment;
    /** @var string Global unique identifier for the process. */
    public readonly string $globallyUniqueString;
    /** @var int The identifier of the process (often called process ID). */
    public readonly int $processIdentifier;
    /** @var string The process name is used to register application defaults and is used in error messages. It does not uniquely identify the process. */
    public string $processName;
    /** @var string Returns the account name of the current user. */
    public readonly string $userName;
    /** @var string Returns the full name of the current user. */
    public readonly string $fullUserName;
    /** @var string The name of the host computer on which the process is executing. */
    public readonly string $hostName;

    public function __construct()
    {
        unset($this->arguments);
        unset($this->environment);
        unset($this->globallyUniqueString);
        unset($this->processIdentifier);
        unset($this->processName);
        unset($this->userName);
        unset($this->fullUserName);
        unset($this->hostName);
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        if ($name == "arguments") {
            $this->$name = new ArrayClass($_SERVER["argv"] ?? []);
            return $this->$name;
        } elseif ($name == "environment") {
            /** @var Dictionary<string> $environment */
            $environment = new Dictionary();
            $fileManager = FileManager::default();
            $url = $fileManager->documentRootDirectory->appendingPathComponent(".env");
            $path = $url->path;
            if ($fileManager->fileExists($path) && ($string = $fileManager->contents($path))) {
                $scanner = new Scanner($string);
                $scanner->charactersToBeSkipped = PHP_EOL;
                while ($scanner->scanUpCharacters(PHP_EOL, $line) && $line) {
                    $components = explode("=", (string) $line, 2);
                    if (count($components) === 2) {
                        [$key, $value] = $components;
                        $environment[trim($key)] = trim($value, "\"' ");
                        $scanner->scanLocation += 1;
                    }
                }
            }
            $this->$name = $environment;
            return $this->$name;
        } elseif ($name == "globallyUniqueString") {
            $this->$name = md5((string)$this->processIdentifier);
            return $this->$name;
        } elseif ($name == "processIdentifier") {
            $this->$name = getmypid();
            return $this->$name;
        } elseif ($name == "processName") {
            $processName = "Unknown";
            /** @psalm-suppress RedundantCondition */
            if (RUNNING_FROM_CLI) {
                $processTitle = cli_get_process_title();
                if ($processTitle !== null) {
                    $processName = $processTitle;
                }
            }
            $this->$name = $processName;
            return $this->$name;
        } elseif ($name == "userName") {
            $this->$name = get_current_user();
            return $this->$name;
        } elseif ($name == "fullUserName") {
            $this->$name = full_user_name();
            return $this->$name;
        } elseif ($name == "hostName") {
            $this->$name = gethostname();
            return $this->$name;
        } else {
            return $this->valueForUndefinedKey($name);
        }
    }

    /**
     * Returns the process information agent for the process.
     */
    public static function processInfo(): ProcessInfo
    {
        if (self::$processInfo === null) {
            self::$processInfo = new ProcessInfo();
        }
        return self::$processInfo;
    }
}
