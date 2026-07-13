<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * A structure that parses URLs into and constructs URLs from their constituent parts.
 */
final class URLComponents extends ObjectClass
{
    /** @var string|null The fragment subcomponent. */
    public ?string $fragment = null;
    /** @var string|null The host subcomponent. */
    public ?string $host = null;
    /** @var string|null The password subcomponent of the URL. */
    public ?string $password = null;
    /** @var string|null The path subcomponent. */
    public ?string $path = null;
    /** @var int|null The port subcomponent. */
    public ?int $port = null;
    /** @var string|null The query subcomponent. */
    public ?string $query = null;
    /** @var string|null The scheme subcomponent of the URL. */
    public ?string $scheme = null;
    /** @var string|null The user subcomponent of the URL. */
    public ?string $user = null;
    /** @var URL|null A URL created from the components. */
    public ?URL $url {
        get => $this->urlRelativeTo(null);
    }
    /** @var string|null A URL derived from the components object, in string form. */
    public ?string $string {
        get {
            $scheme = $this->scheme;
            if ($scheme) {
                $scheme .= "://";
            }
            $user = $this->user;
            $password = $this->password;
            if ($password !== null && $password !== "" && $user !== null && $user !== "") {
                $user .= ":";
                $password = rawurlencode(rawurldecode($password)) . "@";
            } elseif ($user !== null && $user !== "") {
                $user .= "@";
            }
            $host = $this->host;
            $port = $this->port;
            if ($port && $host) {
                $host .= ":";
            }
            $path = $this->path;
            if ($path) {
                $tu = "";
                $tok = strtok($path, "\\/");
                while ($tok !== false) {
                    $tu .= match ($this->scheme) {
                        "http", "https", "ftp", "ftps", "ws", "wss", "file" => (function () use ($tok): string {
                            $tok = rawurldecode($tok);
                            if (!string_contains($tok, "C:", CompareOptions::caseInsensitive)) {
                                $tok = rawurlencode($tok);
                            }
                            return "$tok/";
                        })(),
                        default => "$tok/"
                    };
                    $tok = strtok("\\/");
                }
                $trailingSlash = $tu !== "" && (str_ends_with($path, "/") || str_ends_with($path, "\\"));
                $path = "/" . trim($tu, "/") . ($trailingSlash ? "/" : "");
            }
            $query = $this->query;
            if ($query !== null && $query !== "") {
                $query = "?" . $query;
            }
            $fragment = $this->fragment;
            if ($fragment !== null && $fragment !== "") {
                $fragment = "#" . $fragment;
            }
            $string = new ArrayClass([$scheme, $user, $password, $host, $port, $path, $query, $fragment])->compactMap(fn(string|int|null $element): string|int|null => $element)->join("");
            return empty($string) ? null : $string;
        }
    }
    /** @var ArrayClass<URLQueryItem>|null $queryItems An array of query items for the URL in the order in which they appear in the original query string. Each URLQueryItem represents a single key-value pair, Note that a name may appear more than once in a single query string, so the name values are not guaranteed to be unique. If the URLComponents has an empty query component, it returns an empty array. If the URLComponents has no query component, it returns null. The setter combines an array containing any number of URLQueryItems, each of which represents a single key-value pair, into a query string and sets the URLComponents query property. Passing an empty array sets the query component of the URLComponents to an empty string. Passing null removes the query component of the URLComponents. */
    public ?ArrayClass $queryItems {
        get {
            $query = $this->query;
            if ($query === null || $query === "") {
                return null;
            }
            $components = explode("&", $query);
            if ($components === []) {
                return null;
            }
            return new ArrayClass($components)->map(function (string $pair): URLQueryItem {
                [$name, $value] = explode("=", $pair, 2) + [1 => null];
                if ($value) {
                    $value = htmlspecialchars(urldecode($value), ENT_QUOTES);
                }
                return new URLQueryItem($name, $value);
            });
        }
        set {
            $this->query = null;
            if ($value instanceof ArrayClass) {
                $this->query = http_build_query($value->reduce(new Dictionary(),
                    /**
                     * @param Dictionary<string> $result
                     * @param URLQueryItem $queryItem
                     * @return Dictionary<string>
                     */
                    function (Dictionary $result, URLQueryItem $queryItem): Dictionary {
                        $result[$queryItem->name] = $queryItem->value;
                        return $result;
                    })->array);
            }
        }
    }

    public function __construct(?string $string = null)
    {
        if ($string && ($components = parse_url($string))) {
            foreach ($components as $key => $value) {
                if ($key === "pass") {
                    $key = "password";
                }
                if ($value !== "") {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Returns a URL based on the component settings and relative to a given base URL.
     *
     * If the URLComponents have an authority component (user, password, host or port) and a path component, then the path must either begin with "/" or be an empty string.
     * If the URLComponents does not have an authority component (user, password, host or port) and has a path component, the path component must not start with "//". If those requirements are not met, null is returned.
     */
    public function urlRelativeTo(?URL $baseURL): ?URL
    {
        if ($string = $this->string) {
            return new URL($string, $baseURL);
        }
        return null;
    }
}
