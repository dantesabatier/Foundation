<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Exception;

/** @internal */
final class ClassLoader
{
    private string $autoloadPath {
        get => $this->autoloadPath ??= $this->bundleURL->appendingPathComponent("vendor")->appendingPathComponent("autoload")->appendingPathExtension("php")->path;
    }

    public function __construct(private readonly URL $bundleURL)
    {
    }

    /**
     * Load a class by name.
     * @param string $className Fully qualified class name.
     * @return class-string|null Returns the class string if loaded successfully, or null otherwise.
     */
    public function load(string $className): ?string
    {
        if (class_exists($className)) {
            return $className;
        }
        if (!($fileEnumerator = FileManager::default()->enumerator($this->bundleURL->appendingPathComponent("src"), null, DirectoryEnumerationOptions::skipsHiddenFiles))) {
            return null;
        }
        if (FileManager::default()->fileExists($this->autoloadPath)) {
            require_once $this->autoloadPath;
        }
        $components = new ArrayClass(explode("\\", $className));
        $name = $components->last ?? $className;
        foreach ($fileEnumerator as $url) {
            if (!$this->isCorrectFile($url, $name)) {
                continue;
            }
            $namespace = $this->extractNamespace($url);
            $fullClassName = $namespace ? "$namespace\\$name" : $className;
            if (!class_exists($fullClassName)) {
                continue;
            }
            return $fullClassName;
        }
        return null;
    }

    private function isCorrectFile(URL $url, string $name): bool
    {
        return string_is_equal($url->pathExtension, "php", CompareOptions::caseInsensitive) && string_is_equal(FileManager::default()->displayName($url->path), $name, CompareOptions::caseInsensitive);
    }

    private function extractNamespace(URL $url): ?string
    {
        try {
            $contents = FileManager::default()->contents($url->path);
            if ($contents) {
                preg_match("/\s*namespace\s+([^;]+);/", $contents, $matches);
                return isset($matches[1]) ? trim($matches[1]) : null;
            }
        } catch (Exception) {
            // Ignore errors during namespace extraction
        }
        return null;
    }
}
