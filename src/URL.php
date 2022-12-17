<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 02/07/20
 * Time: 10:16
 */

namespace Sabatier\Foundation;

use Closure;
use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\ExpectedValues;
use SplFileInfo;

/**
 * A value that identifies the location of a resource, such as an item on a remote server or the path to a local file.
 * @property-read string $absoluteString The absolute string for the URL.
 * @property-read URL $absoluteURL The absolute URL.
 * @property-read URL|null $baseURL The base URL.
 * @property-read string|null $fragment The fragment component of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise nil.
 * @property-read string|null $host The host component of a URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise nil.
 * @property-read string $lastPathComponent The last path component of the URL, or an empty string if the path is an empty string.
 * @property-read string $path The path component of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise an empty string.
 * @property-read ArrayClass<string> $pathComponents The path components of the URL, or an empty array if the path is an empty string.
 * @property-read string $pathExtension The path extension of the URL, or an empty string if the path is an empty string.
 * @property-read int|null $port The port component of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise nil.
 * @property-read string|null $query The query of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise nil.
 * @property-read string $relativePath The relative path of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise nil.
 * @property-read string $relativeString The relative portion of a URL.
 * @property-read string $scheme The scheme of the URL.
 * @property-read URL $standardized A version of the URL with any instances of “..” or “.” removed from its path.
 * @property-read URL $standardizedFileURL A standardized version of the path of a file URL.
 * @property-read string|null $user The user component of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise nil.
 * @property-read string|null $password The password component of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise nil.
 * @property-read bool $isFileURL A Boolean that is true if the scheme is "file".
 * @property-read bool $hasDirectoryPath A Boolean that is true if the URL path represents a directory.
 * @property-read string $fileSystemRepresentation A string containing the URL's file system path.
 */
final class URL extends ObjectClass
{
    private string $string;
    private ?URLResourceValuesStorage $storage = null;

    /**
     * Creates a URL instance from the provided string, relative to another URL.
     * @param string $string The URL string with which to initialize the URL object.
     * @param URL|null $baseURL The base URL for the URL object.
     */
    public function __construct(string $string, private ?URL $baseURL = null)
    {
        if ($this->baseURL === null) {
            if ($string) {
                $components = new URLComponents($string);
                $proposed = $components->string;
                if ($proposed) {
                    $string = $proposed;
                }
            }
            if (!url_validate($string)) {
                throw new InvalidArgumentException(sprintf("Invalid argument: expecting url string, \"%s\" given", $string));
            }
        }
        $this->string = $string;
    }

    #[ArrayShape(["string" => "string", "baseURL" => "\\" . URL::class])]
    public function __serialize(): array
    {
        $serialization = ["string" => $this->string];
        if ($baseURL = $this->baseURL) {
            $serialization["baseURL"] = $baseURL;
        }
        return $serialization;
    }

    public function __unserialize(array $data): void
    {
        $this->string = $data["string"];
        $this->baseURL = $data["baseURL"] ?? null;
    }

    public function __get(string $name)
    {
        if ($name == "absoluteURL") {
            $baseURL = $this->baseURL;
            if (!$baseURL instanceof URL) {
                return $this;
            }
            if (!$baseURL->hasDirectoryPath) {
                $baseURL = $baseURL->deletingLastPathComponent();
            }
            $relative = $this->string;
            if (string_has_prefix($relative, "/")) {
                $relative = substring_from_index($relative, 1);
            } elseif (string_has_prefix($relative, "./")) {
                $relative = substring_from_index($relative, 2);
            } elseif (string_has_prefix($relative, "../")) {
                $steps = substr_count($relative, "../");
                $numberOfComponents = $baseURL->pathComponents->count();
                if ($steps >= $numberOfComponents) {
                    trigger_error("{$this->debugDescription()} components in the relative url are too many", E_USER_WARNING);
                    $steps = ($numberOfComponents - 1);
                }
                while ($steps > 0) {
                    $baseURL = $baseURL->deletingLastPathComponent();
                    $relative = substring_from_index($relative, 3);
                    $steps--;
                }
            }
            if (empty($relative)) {
                return $baseURL;
            }
            return $baseURL->appendingPathComponent($relative);
        } elseif ($name == "absoluteString") {
            if ($this->baseURL === null) {
                return $this->string;
            }
            return $this->absoluteURL->absoluteString;
        } elseif ($name == "relativePath") {
            if ($this->baseURL === null) {
                return $this->path;
            }
            return $this->absoluteURL->path;
        } elseif ($name == "relativeString") {
            if ($this->baseURL === null) {
                return $this->absoluteString;
            }
            return $this->absoluteURL->absoluteString;
        } elseif ($name == "fileSystemRepresentation") {
            return (new SplFileInfo($this->path))->getRealPath();
        } elseif ($name == "fragment") {
            return $this->parse(PHP_URL_FRAGMENT);
        } elseif ($name == "standardized") {
            $url = clone $this->absoluteURL;
            $url->standardize();
            return $url;
        } elseif ($name == "standardizedFileURL") {
            return $this->standardized;
        } elseif ($name == "scheme") {
            return $this->parse(PHP_URL_SCHEME) ?? "";
        } elseif ($name == "host") {
            return $this->parse(PHP_URL_HOST);
        } elseif ($name == "lastPathComponent") {
            return basename($this->path);
        } elseif ($name == "path") {
            $path = $this->parse(PHP_URL_PATH) ?? "";
            if ($this->isFileURL) {
                $path = rawurldecode($path);
            }
            return $path;
        } elseif ($name == "pathComponents") {
            $path = $this->path;
            /** @var ArrayClass<string> $components */
            $components = new ArrayClass();
            if (string_has_prefix($path, "/")) {
                $components->append("/");
            }
            $components->appendContentsOf((new ArrayClass(explode("/", $path)))->filter(fn(string $component): bool => !empty($component)));
            if ($components->count() > 1 && string_has_suffix($path, "/")) {
                $components->append("/");
            }
            return $components;
        } elseif ($name == "pathExtension") {
            return pathinfo($this->path, PATHINFO_EXTENSION);
        } elseif ($name == "port") {
            return $this->parse(PHP_URL_PORT);
        } elseif ($name == "query") {
            return $this->parse(PHP_URL_QUERY);
        } elseif ($name == "user") {
            return $this->parse(PHP_URL_USER);
        } elseif ($name == "password") {
            return $this->parse(PHP_URL_PASS);
        } elseif ($name == "isFileURL") {
            return $this->scheme === "file";
        } elseif ($name == "hasDirectoryPath") {
            return $this->isFileURL && is_dir($this->path) || $this->pathExtension === "";
        } elseif ($name == "baseURL") {
            return $this->$name;
        } else {
            return $this->valueForUndefinedKey($name);
        }
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    private function parse(int $component): mixed
    {
        $v = parse_url($this->absoluteString, $component);
        if (empty($v)) {
            return null;
        }
        return $v;
    }

    private function rebuild(string $path): void
    {
        $components = new URLComponents();
        $components->scheme = $this->scheme;
        $components->user = $this->user;
        $components->password = $this->password;
        $components->host = $this->host;
        $components->port = $this->port;
        $components->path = $path;
        $components->query = $this->query;
        $components->fragment = $this->fragment;
        $this->string = $components->string ?? throw new InvalidArgumentException();
    }

    private function storage(): URLResourceValuesStorage
    {
        if ($this->storage === null) {
            $this->storage = new URLResourceValuesStorage();
        }
        return $this->storage;
    }

    /**
     * Creates a file URL that references the local file or directory at path.
     * @param string $path The path that the URL object will represent. path should be a valid system path, and must not be an empty path. If path begins with a tilde, it must first be expanded with expandingTildeInPath. If path is a relative path, it is treated as being relative to the current working directory.
     * @return URL A URL object initialized with path.
     */
    public static function fileURL(string $path): URL
    {
        $string = "";
        if (/** @phpstan-ignore-line */ TARGET_OS_WINDOWS) {
            $path = str_replace("\\", "/", (string)parse_url($path, PHP_URL_PATH));
        }
        $scheme = parse_url($path, PHP_URL_SCHEME);
        if (empty($scheme) && !empty($path)) {
            $scheme = "file";
            $string .= "$scheme:";
        }
        if ($scheme !== "file") {
            throw new InvalidArgumentException("Invalid url scheme \"$scheme\"");
        }
        $string .= "//$path";
        return new URL($string);
    }

    /**
     * Appends a path component to the URL.
     * @param string $component The path component to add to the URL, in its original form (not URL encoded).
     */
    public function appendPathComponent(string $component): URL
    {
        $path = $this->path;
        if (!string_has_suffix($path, "/")) {
            if ($this->isFileURL && FileManager::default()->fileExists($path, $isDirectory) && !$isDirectory) {
                throw new InternalInconsistencyException("Cannot append components to a file");
            }
            $path .= "/";
        }
        $path .= $component;
        $this->rebuild($path);
        return $this;
    }

    /**
     * Returns a URL constructed by appending the given path component to self.
     * @param string $component The path component to add to the URL, in its original form (not URL encoded).
     */
    public function appendingPathComponent(string $component): URL
    {
        $url = clone $this->absoluteURL;
        $url->appendPathComponent($component);
        return $url;
    }

    /**
     * Appends the given path extension to self.
     * @param string $extension The path extension to add to the URL.
     */
    public function appendPathExtension(string $extension): URL
    {
        if (strlen($extension)) {
            $path = $this->path;
            if (!string_has_suffix($path, ".") && !string_has_prefix($extension, ".")) {
                $path .= ".";
            }
            $path .= $extension;
            $this->rebuild($path);
        }
        return $this;
    }

    /**
     * Returns a URL constructed by appending the given path extension to self.
     * @param string $extension The path extension to add to the URL.
     */
    public function appendingPathExtension(string $extension): URL
    {
        $url = clone $this->absoluteURL;
        $url->appendPathExtension($extension);
        return $url;
    }

    /**
     * Returns a URL constructed by removing the last path component of self.
     */
    public function deleteLastPathComponent(): URL
    {
        $this->string = str_replace($this->lastPathComponent, "", $this->string);
        if ($this->pathComponents->count() > 1) {
            $this->string = rtrim($this->string, "/.");
        }
        return $this;
    }

    /**
     * Returns a URL constructed by removing the last path component of self.
     */
    public function deletingLastPathComponent(): URL
    {
        $url = clone $this->absoluteURL;
        $url->deleteLastPathComponent();
        return $url;
    }

    /**
     * Returns a URL constructed by removing any path extension.
     */
    public function deletePathExtension(): URL
    {
        $this->string = rtrim(str_replace($this->pathExtension, "", $this->string), "/.");
        return $this;
    }

    /**
     * Returns a URL constructed by removing any path extension.
     */
    public function deletingPathExtension(): URL
    {
        $url = clone $this->absoluteURL;
        $url->deletePathExtension();
        return $url;
    }

    public function removingPercentEncoding(): string
    {
        return urldecode($this->absoluteString);
    }

    /**
     * Return a collection of resource values identified by the given resource keys.
     *
     * This method first checks if the URL object already caches the resource value. If so, it returns the cached resource value to the caller. If not, then this method synchronously obtains the resource value from the backing store, adds the resource value to the URL object's cache, and returns the resource value to the caller. The type of the resource value varies by resource property (see {@see URLResourceKey}). If this method does not throw and the resulting value in the URLResourceValues is populated with nil, it means the resource property is not available for the specified resource and no errors occurred when determining the resource property was not available. This method is currently applicable only to URLs for file system resources.
     * Only the values for the keys specified in keys will be populated.
     * @param Set<string> $keys
     * @return URLResourceValues
     * @throws Exception
     */
    public function resourceValues(Set $keys): URLResourceValues
    {
        return new URLResourceValues($keys, $this->storage()->resourceValues($keys, $this));
    }

    /**
     * Returns the value of the resource property for the specified key.
     *
     * This method first checks if the URL object already caches the resource value. If so, it returns the cached resource value to the caller. If not, then this method synchronously obtains the resource value from the backing store, adds the resource value to the URL object's cache, and returns the resource value to the caller.
     * The type of the returned resource value varies by resource property; for details, see the documentation for the key you want to access.
     * If this method returns true and the value is populated with nil, it means that the resource property is not available for the specified resource, and that no errors occurred when determining that the resource property was unavailable.
     * @param mixed $value The location where the value for the resource property identified by key should be stored.
     * @param string $key The name of one of the URL's resource properties.
     * @throws Exception
     */
    public function getResourceValue(mixed &$value, #[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key): void
    {
        $this->storage()->getResourceValue($value, $key, $this);
    }

    /**
     * Sets the resource value identified by a given resource key.
     *
     * This method writes the new resource values out to the backing store. Attempts to set a read-only resource property or to set a resource property not supported by the resource are ignored and are not considered errors. This method is currently applicable only to URLs for file system resources.
     * URLResourceValues keeps track of which of its properties have been set. Those values are the ones used by this function to determine which properties to write.
     * @throws Exception
     */
    public function setResourceValues(URLResourceValues $values): void
    {
        $this->storage()->setResourceValues($values->allValues, $this);
    }

    /**
     * Removes the cached resource value identified by a given resource value key from the URL object.
     *
     * Removing a cached resource value may remove other cached resource values because some resource values are cached as a set of values, and because some resource values depend on other resource values (temporary resource values have no dependencies). This method is currently applicable only to URLs for file system resources.
     */
    public function removeCachedResourceValue(#[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key): void
    {
        $this->storage()->removeCachedResourceValue($key);
    }

    /**
     * Removes all cached resource values and all temporary resource values from the URL object.
     * This method is currently applicable only to URLs for file system resources.
     */
    public function removeAllCachedResourceValues(): void
    {
        $this->storage()->removeAllCachedResourceValues();
    }

    /**
     * Sets a temporary resource value on the URL object.
     *
     * Temporary resource values are for client use. Temporary resource values exist only in memory and are never written to the resource's backing store. Once set, a temporary resource value can be copied from the URL object with func {@see resourceValues()}. The values are stored in the loosely-typed allValues dictionary property.
     * To remove a temporary resource value from the URL object, use func {@see removeCachedResourceValue()}. Care should be taken to ensure the key that identifies a temporary resource value is unique and does not conflict with system defined keys (using reverse domain name notation in your temporary resource value keys is recommended). This method is currently applicable only to URLs for file system resources.
     */
    public function setTemporaryResourceValue(mixed $value, #[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key): void
    {
        $this->storage()->setTemporaryResourceValue($value, $key);
    }

    /**
     * @template ResultType
     * Passes the URL's path in the file system representation to a closure.
     * @param Closure(string): ResultType $block A closure to execute, which receives a string as its parameter, and returns a value of a type you choose.
     * The parameter passed to the closure is nil if the URL cannot be represented by the file system. For example, if the URL contains an accented character and the file system only supports ASCII, no file system representation is possible.
     * @return ResultType
     */
    public function withUnsafeFileSystemRepresentation(Closure $block)
    {
        return $block($this->fileSystemRepresentation);
    }

    /**
     * Resolves any symlinks in the path of a file URL.
     *
     * If the isFileURL is false, this method does nothing.
     */
    public function resolveSymlinksInPath(): URL
    {
        if ($this->isFileURL) {
            $this->string = (URL::fileURL(readlink($this->path)))->string;
            $this->baseURL = null;
        }
        return $this;
    }

    /**
     * Resolves any symlinks in the path of a file URL.
     */
    public function resolvingSymlinksInPath(): URL
    {
        if (!$this->isFileURL) {
            return $this;
        }
        $url = clone $this->absoluteURL;
        $url->resolveSymlinksInPath();
        return $url;
    }

    /**
     * Standardizes the path of a file URL.
     */
    public function standardize(): URL
    {
        if ($this->isFileURL) {
            $this->string = (URL::fileURL(realpath($this->path)))->string;
            $this->baseURL = null;
        }
        return $this;
    }

    public function description(): string
    {
        return $this->absoluteString;
    }

    public function jsonSerialize(): string
    {
        return $this->description();
    }

    public function isEqual(mixed $other): bool
    {
        if ($other instanceof URL) {
            return string_is_equal($this->absoluteString, $other->absoluteString, CompareOptions::caseInsensitive);
        }
        return false;
    }
}
