<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 02/07/20
 * Time: 10:16
 */

namespace Sabatier\Foundation;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;
use Override;
use SplFileInfo;

/**
 * A value that identifies the location of a resource, such as an item on a remote server or the path to a local file.
 */
final class URL extends ObjectClass
{
    private const array ENCODED_PATH_SCHEMES = ["http", "https", "ftp", "ftps", "ws", "wss", "file"];

    private string $string;
    /** @var array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string}|null The cached raw components of the absolute string, as returned by parse_url(). */
    private ?array $components = null;
    /** @var string The absolute string for the URL. */
    public string $absoluteString {
        get {
            if ($this->baseURL === null) {
                return $this->string;
            }
            return $this->absoluteURL->absoluteString;
        }
    }
    /** @var URL The absolute URL. Relative references are resolved against baseURL following RFC 3986 section 5. */
    public URL $absoluteURL {
        get {
            if ($this->baseURL === null) {
                return $this;
            }
            $reference = parse_url($this->string) ?: [];
            if (isset($reference["scheme"])) {
                return new URL($this->string);
            }
            $base = $this->baseURL->absoluteURL;
            if (isset($reference["host"])) {
                return new URL($base->scheme . ":" . $this->string);
            }
            $components = $base->parsed();
            $referencePath = isset($reference["path"]) ? (string)$reference["path"] : "";
            if ($referencePath !== "") {
                if (str_starts_with($referencePath, "/")) {
                    $path = self::removeDotSegments($referencePath);
                } else {
                    $basePath = isset($components["path"]) ? (string)$components["path"] : "";
                    $slash = strrpos($basePath, "/");
                    $path = self::removeDotSegments(($slash === false ? "/" : substr($basePath, 0, $slash + 1)) . $referencePath);
                }
                $components["path"] = $path;
                unset($components["query"]);
            }
            if (isset($reference["query"])) {
                $components["query"] = (string)$reference["query"];
            }
            unset($components["fragment"]);
            if (isset($reference["fragment"])) {
                $components["fragment"] = (string)$reference["fragment"];
            }
            return new URL(self::stringFromComponents($components));
        }
    }
    /** @var string The path of the relative portion of the URL, or the full path if the URL is not relative to a base URL. */
    public string $relativePath {
        get {
            $path = parse_url($this->string, PHP_URL_PATH);
            return is_string($path) ? rawurldecode($path) : "";
        }
    }
    /** @var string The relative portion of a URL. If the URL was created without a base URL, this is the same as absoluteString. */
    public string $relativeString {
        get => $this->string;
    }
    /** @var string A string containing the URL's file system path, in the platform's native form. */
    public string $fileSystemRepresentation {
        get {
            $path = self::nativePath($this->path);
            $resolved = new SplFileInfo($path)->getRealPath();
            return $resolved === false ? $path : $resolved;
        }
    }
    /** @var string|null The fragment component of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise null. */
    public ?string $fragment {
        get {
            $fragment = $this->component("fragment");
            return $fragment === null ? null : rawurldecode($fragment);
        }
    }
    /** @var URL A version of the URL with any instances of ".." or "." removed from its path. */
    public URL $standardized {
        get {
            $url = clone $this->absoluteURL;
            $url->standardize();
            return $url;
        }
    }
    /** @var URL A standardized version of the path of a file URL. */
    public URL $standardizedFileURL {
        get => $this->standardized;
    }
    /** @var string The scheme of the URL. */
    public string $scheme {
        get => $this->component("scheme") ?? "";
    }
    /** @var string|null The host component of a URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise null. */
    public ?string $host {
        get {
            $host = $this->component("host");
            return $host === null ? null : rawurldecode($host);
        }
    }
    /** @var string The last path component of the URL, or an empty string if the path is an empty string. */
    public string $lastPathComponent {
        get {
            $path = $this->path;
            return $path === "/" ? "/" : basename($path);
        }
    }
    /** @var string The path component of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise an empty string. The path is percent-decoded. */
    public string $path {
        get => rawurldecode($this->rawPath());
    }
    /** @var ArrayClass<string> $pathComponents The path components of the URL, or an empty array if the path is an empty string. */
    public ArrayClass $pathComponents {
        get {
            $path = $this->path;
            /** @var ArrayClass<string> $components */
            $components = new ArrayClass();
            if (str_starts_with($path, "/")) {
                $components->append("/");
            }
            $components->appendContentsOf(new ArrayClass(explode("/", $path))->filter(fn(string $component): bool => !empty($component)));
            if ($components->count > 1 && str_ends_with($path, "/")) {
                $components->append("/");
            }
            return $components;
        }
    }
    /** @var string The path extension of the URL, or an empty string if the path is an empty string. */
    public string $pathExtension {
        get => pathinfo($this->path, PATHINFO_EXTENSION);
    }
    /** @var int|null The port component of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise null. */
    public ?int $port {
        get {
            $port = $this->parsed()["port"] ?? null;
            return $port === null ? null : (int)$port;
        }
    }
    /** @var string|null The query of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise null. The query keeps its percent-encoding, since decoding it can change its structure (an encoded "&" or "=" would become a separator). */
    public ?string $query {
        get => $this->component("query");
    }
    /** @var string|null The user component of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise null. */
    public ?string $user {
        get {
            $user = $this->component("user");
            return $user === null ? null : rawurldecode($user);
        }
    }
    /** @var string|null The password component of the URL if the URL conforms to RFC 1808 (the most common form of URL), otherwise null. */
    public ?string $password {
        get {
            $password = $this->component("pass");
            return $password === null ? null : rawurldecode($password);
        }
    }
    /** @var bool A Boolean that is true if the scheme is "file". */
    public bool $isFileURL {
        get => $this->scheme === "file";
    }
    /** @var bool A Boolean that is true if the URL path represents a directory. */
    public bool $hasDirectoryPath {
        get {
            if ($this->isFileURL) {
                $path = self::nativePath($this->path);
                if (file_exists($path)) {
                    return is_dir($path);
                }
            }
            return str_ends_with($this->rawPath(), "/") || $this->pathExtension === "";
        }
    }
    private URLResourceValuesStorage $storage {
        get => $this->storage ??= new URLResourceValuesStorage();
    }
    #[Override]
    public string $description {
        get => $this->absoluteString;
    }

    /**
     * Creates a URL instance from the provided string, relative to another URL.
     * @param string $string The URL string with which to initialize the URL object.
     * @param URL|null $baseURL The base URL for the URL object.
     */
    public function __construct(string $string, private(set) ?URL $baseURL = null)
    {
        if ($this->baseURL === null) {
            if ($string) {
                $components = new URLComponents($string);
                $proposed = $components->string;
                if ($proposed) {
                    $string = $proposed;
                }
            }
            if (!is_parseable_url($string)) {
                fatal_error("Invalid argument: expecting url string, \"$string\" given");
            }
        }
        $this->string = $string;
    }

    public function __serialize(): array
    {
        return ["string" => $this->string, "baseURL" => $this->baseURL];
    }

    public function __unserialize(array $data): void
    {
        $this->string = $data["string"];
        $this->baseURL = $data["baseURL"];
        $this->components = null;
    }

    /**
     * Returns the raw (still percent-encoded) components of the absolute string, caching the parse.
     * @return array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string}
     */
    private function parsed(): array
    {
        return $this->components ??= (parse_url($this->absoluteString) ?: []);
    }

    /** Returns the named raw component of the absolute string, or null if it is absent. */
    private function component(/** @noinspection PhpSameParameterValueInspection */ string $name): ?string
    {
        $value = $this->parsed()[$name] ?? null;
        return $value === null ? null : (string)$value;
    }

    /** Returns the raw (still percent-encoded) path of the absolute string. */
    private function rawPath(): string
    {
        return $this->component("path") ?? "";
    }

    /**
     * Reassembles a URL string from raw parse_url()-style components. No encoding or decoding is
     * performed, so components round-trip byte for byte.
     * @param array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string} $components
     */
    private static function stringFromComponents(array $components): string
    {
        $string = "";
        if (isset($components["scheme"])) {
            $string .= $components["scheme"] . "://";
        }
        if (isset($components["user"])) {
            $string .= $components["user"];
            if (isset($components["pass"])) {
                $string .= ":" . $components["pass"];
            }
            $string .= "@";
        }
        $string .= $components["host"] ?? "";
        if (isset($components["port"])) {
            $string .= ":" . $components["port"];
        }
        $path = $components["path"] ?? "";
        // On Windows, parse_url() strips the slash before a drive letter ("file:///C:/x" parses
        // to path "C:/x"); reinsert it so the authority and path stay separated.
        if ($path !== "" && !str_starts_with($path, "/")) {
            $path = "/" . $path;
        }
        $string .= $path;
        if (isset($components["query"])) {
            $string .= "?" . $components["query"];
        }
        if (isset($components["fragment"])) {
            $string .= "#" . $components["fragment"];
        }
        return $string;
    }

    /** Replaces the path of the URL, keeping every other component intact. The URL becomes absolute. */
    private function rebuild(string $path): void
    {
        $components = $this->parsed();
        $components["path"] = $path;
        $this->string = self::stringFromComponents($components);
        $this->baseURL = null;
        $this->components = null;
    }

    /** Removes "." and ".." segments from a path, per RFC 3986 section 5.2.4. */
    private static function removeDotSegments(string $path): string
    {
        $input = $path;
        $output = "";
        while ($input !== "") {
            if (str_starts_with($input, "../")) {
                $input = substr($input, 3);
            } elseif (str_starts_with($input, "./")) {
                $input = substr($input, 2);
            } elseif (str_starts_with($input, "/./")) {
                $input = substr($input, 2);
            } elseif ($input === "/.") {
                $input = "/";
            } elseif (str_starts_with($input, "/../")) {
                $input = substr($input, 3);
                $output = substr($output, 0, (int)strrpos($output, "/"));
            } elseif ($input === "/..") {
                $input = "/";
                $output = substr($output, 0, (int)strrpos($output, "/"));
            } elseif ($input === "." || $input === "..") {
                $input = "";
            } else {
                preg_match("#^/?[^/]*#", $input, $matches);
                $output .= $matches[0];
                $input = substr($input, strlen($matches[0]));
            }
        }
        return $output;
    }

    /**
     * Converts a URL path such as "/C:/Users/dante" into the platform-native form "C:/Users/dante".
     * Paths that do not carry a Windows drive prefix are returned unchanged.
     */
    private static function nativePath(string $path): string
    {
        if (preg_match("#^/[A-Za-z]:#", $path) === 1) {
            return substr($path, 1);
        }
        return $path;
    }

    /**
     * Percent-encodes each segment of a path component for schemes whose paths are expected to be
     * encoded, leaving Windows drive segments and already-encoded input intact.
     */
    private function encodedPathComponent(string $component): string
    {
        if (!in_array($this->scheme, self::ENCODED_PATH_SCHEMES, true)) {
            return $component;
        }
        return explode("/", $component)
                |> (fn(array $x): array => array_map(static function (string $segment): string {
                    if ($segment === "" || preg_match("#^[A-Za-z]:$#", $segment) === 1) {
                        return $segment;
                    }
                    return rawurlencode(rawurldecode($segment));
                }, $x))
                |> (fn(array $x): string => implode("/", $x));
    }

    /**
     * Creates a file URL that references the local file or directory at $path.
     * @param string $path The path that the URL object will represent path should be a valid system path and must not be an empty path. If $path begins with a tilde, it must first be expanded with expandingTildeInPath. If $path is a relative path, it is treated as being relative to the current working directory.
     * @param URL|null $base A URL that provides a file system location that the path extends.
     * @return URL A URL object initialized with $path.
     */
    public static function fileURL(string $path, ?URL $base = null): URL
    {
        $path = str_replace("\\", "/", $path);
        if (!str_starts_with($path, "/")) {
            $path = "/$path";
        }
        return new URL("file://$path", $base);
    }

    /**
     * Appends a path component to the URL.
     * @param string $component The path component to add to the URL, in its original form (not URL encoded).
     */
    public function appendPathComponent(string $component): URL
    {
        $path = $this->rawPath();
        if (!str_ends_with($path, "/")) {
            if ($this->isFileURL && FileManager::default()->fileExists(self::nativePath($this->path), $isDirectory) && !$isDirectory) {
                fatal_error("Cannot append components to a file");
            }
            $path .= "/";
        }
        $path .= $this->encodedPathComponent(ltrim($component, "/"));
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
            $rawPath = $this->rawPath();
            $path = rtrim($rawPath, "/");
            if ($path === "") {
                return $this;
            }
            if (!str_ends_with($path, ".") && !str_starts_with($extension, ".")) {
                $path .= ".";
            }
            $path .= $extension;
            if (str_ends_with($rawPath, "/")) {
                $path .= "/";
            }
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
     * Removes the last path component of self. The other components of the URL (host, query,
     * fragment, ...) are left intact. A URL whose path is empty or "/" is returned unchanged.
     */
    public function deleteLastPathComponent(): URL
    {
        $trimmed = rtrim($this->rawPath(), "/");
        if ($trimmed === "") {
            return $this;
        }
        $slash = strrpos($trimmed, "/");
        $path = $slash === false ? "" : substr($trimmed, 0, $slash);
        $this->rebuild($path === "" ? "/" : $path);
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
     * Removes any path extension of the last path component of self. The other components of the
     * URL are left intact.
     */
    public function deletePathExtension(): URL
    {
        $path = $this->rawPath();
        $extension = pathinfo(rtrim($path, "/"), PATHINFO_EXTENSION);
        if ($extension !== "") {
            $this->rebuild((string)preg_replace("#\\." . preg_quote($extension, "#") . "(/*)\$#", "\$1", $path));
        }
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
        return rawurldecode($this->absoluteString);
    }

    /**
     * Return a collection of resource values identified by the given resource keys.
     *
     * This method first checks if the URL object already caches the resource value. If so, it returns the cached resource value to the caller. If not, then this method synchronously gets the resource value from the backing store, adds the resource value to the URL object's cache, and returns the resource value to the caller. The type of the resource value varies by resource property (see {@see URLResourceKey}). If this method does not throw and the resulting value in the URLResourceValues is populated with null, it means the resource property is not available for the specified resource and no errors occurred when determining the resource property was not available. This method is currently applicable only to URLs for file system resources.
     * Only the values for the keys specified in keys will be populated.
     * @param Set<string> $keys
     * @return URLResourceValues
     */
    public function resourceValues(Set $keys): URLResourceValues
    {
        return new URLResourceValues($keys, $this->storage->resourceValues($keys, $this));
    }

    /**
     * Returns the value of the resource property for the specified key.
     *
     * This method first checks if the URL object already caches the resource value. If so, it returns the cached resource value to the caller. If not, then this method synchronously gets the resource value from the backing store, adds the resource value to the URL object's cache, and returns the resource value to the caller.
     * The type of the returned resource value varies by resource property; for details, see the documentation for the key you want to access.
     * If this method returns true and the value is populated with null, it means that the resource property is not available for the specified resource, and that no errors occurred when determining that the resource property was unavailable.
     * @param mixed $value The location where the value for the resource property identified by $key should be stored.
     * @param string $key The name of one of the URL's resource properties.
     */
    public function getResourceValue(mixed &$value, #[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key): void
    {
        $this->storage->getResourceValue($value, $key, $this);
    }

    /**
     * Sets the resource value identified by a given resource key.
     *
     * This method writes the new resource values out to the backing store. Attempts to set a read-only resource property or to set a resource property not supported by the resource are ignored and are not considered errors. This method is currently applicable only to URLs for file system resources.
     * URLResourceValues keeps track of which of its properties have been set. Those values are the ones used by this function to determine which properties to write.
     */
    public function setResourceValues(URLResourceValues $values): void
    {
        $this->storage->setResourceValues($values->allValues, $this);
    }

    /**
     * Removes the cached resource value identified by a given resource value key from the URL object.
     *
     * Removing a cached resource value may remove other cached resource values because some resource values are cached as a set of values and because some resource values depend on other resource values (temporary resource values have no dependencies). This method is currently applicable only to URLs for file system resources.
     */
    public function removeCachedResourceValue(#[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key): void
    {
        $this->storage->removeCachedResourceValue($key);
    }

    /**
     * Removes all cached resource values and all temporary resource values from the URL object.
     * This method is currently applicable only to URLs for file system resources.
     */
    public function removeAllCachedResourceValues(): void
    {
        $this->storage->removeAllCachedResourceValues();
    }

    /**
     * Sets a temporary resource value on the URL object.
     *
     * Temporary resource values are for client use. Temporary resource values exist only in memory and are never written to the resource's backing store. Once set, a temporary resource value can be copied from the URL object with func {@see resourceValues()}. The values are stored in the loosely typed allValues dictionary property.
     * To remove a temporary resource value from the URL object, use func {@see removeCachedResourceValue()}. Care should be taken to ensure the key that identifies a temporary resource value is unique and does not conflict with system-defined keys (using reverse domain name notation in your temporary resource value keys is recommended). This method is currently applicable only to URLs for file system resources.
     */
    public function setTemporaryResourceValue(mixed $value, #[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key): void
    {
        $this->storage->setTemporaryResourceValue($value, $key);
    }

    /**
     * @template ResultType
     * Passes the URL's path in the file system representation to a closure.
     * @param Closure(string): ResultType $block A closure to execute, which receives the file system representation of the URL as its parameter and returns a value of a type you choose.
     * @return ResultType
     */
    public function withUnsafeFileSystemRepresentation(Closure $block)
    {
        return $block($this->fileSystemRepresentation);
    }

    /**
     * Resolves any symlinks in the path of a file URL.
     *
     * If isFileURL is false, or the path does not exist on disk, this method does nothing.
     */
    public function resolveSymlinksInPath(): URL
    {
        if ($this->isFileURL && ($resolved = realpath(self::nativePath($this->path))) !== false) {
            $this->string = URL::fileURL($resolved)->string;
            $this->baseURL = null;
            $this->components = null;
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
     * Standardizes the path of the URL by lexically removing any "." and ".." segments,
     * per RFC 3986 section 5.2.4. The path does not need to exist on disk.
     */
    public function standardize(): URL
    {
        $path = $this->rawPath();
        $standardized = self::removeDotSegments($path);
        if ($standardized !== $path) {
            $this->rebuild($standardized);
        }
        return $this;
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->description;
    }

    #[Override]
    public function compare(mixed $other): ComparisonResult
    {
        if ($other instanceof URL) {
            $options = $this->isFileURL && $other->isFileURL ? CompareOptions::caseInsensitive : CompareOptions::none;
            return ComparisonResult::from(string_compare($this->canonicalString(), $other->canonicalString(), $options));
        }
        return ComparisonResult::orderedDescending;
    }

    /**
     * Returns the absolute string with the scheme and host lowercased, so that comparisons treat
     * only those components as case-insensitive, per RFC 3986 section 6.2.2.1.
     */
    private function canonicalString(): string
    {
        $components = $this->absoluteURL->parsed();
        if (isset($components["scheme"])) {
            $components["scheme"] = strtolower($components["scheme"]);
        }
        if (isset($components["host"])) {
            $components["host"] = strtolower($components["host"]);
        }
        return self::stringFromComponents($components);
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        return $this->compare($other) === ComparisonResult::orderedSame;
    }
}
