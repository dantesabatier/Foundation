<?php

namespace Sabatier\Foundation;

/**
 * A structure that parses URLs into and constructs URLs from their constituent parts.
 * @property-read URL|null $url A URL created from the components.
 * @property-read string|null $string A URL derived from the components object, in string form.
 * @property ArrayClass<URLQueryItem>|null $queryItems An array of query items for the URL in the order in which they appear in the original query string. Each URLQueryItem represents a single key-value pair, Note that a name may appear more than once in a single query string, so the name values are not guaranteed to be unique. If the URLComponents has an empty query component, returns an empty array. If the URLComponents has no query component, returns nil. The setter combines an array containing any number of URLQueryItems, each of which represents a single key-value pair, into a query string and sets the URLComponents query property. Passing an empty array sets the query component of the URLComponents to an empty string. Passing nil removes the query component of the URLComponents.
 */
class URLComponents extends ObjectClass
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

    public function __construct(?string $string = null)
    {
        if ($string && ($components = parse_url($string))) {
            foreach ($components as $key => $value) {
                if (!empty($value)) {
                    $this->$key = $value;
                }
            }
        }
    }

    public function __get(string $name)
    {
        if ($name == "url") {
            return $this->urlRelativeTo(null);
        } elseif ($name == "string") {
            $scheme = $this->scheme;
            if ($scheme) {
                $scheme .= "://";
            }
            $user = $this->user;
            $password = $this->password;
            if ($password && $user) {
                $user .= ":";
                $password = rawurlencode($password) . "@";
            } elseif ($user) {
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
                while (strlen($tok)) {
                    $tu .= match ($this->scheme) {
                        "http", "https", "ftp", "ftps", "ws", "wss", "file" => rawurlencode(rawurldecode($tok)) . "/",
                        default => "$tok/"
                    };
                    $tok = strtok("\\/");
                }
                $path = "/" . trim($tu, "/");
            }
            $query = $this->query;
            if ($query) {
                $query = "?" . $query;
            }
            $fragment = $this->fragment;
            if ($fragment) {
                $fragment = "#" . $fragment;
            }
            $string = (new ArrayClass([$scheme, $user, $password, $host, $port, $path, $query, $fragment]))->compactMap(fn(string|int|null $element): string|int|null => $element)->join("");
            return empty($string) ? null : $string;
        } elseif ($name == "queryItems") {
            return ($this->query === null) ? null : (new ArrayClass(explode("&", $this->query)))->map(function (string $pair): URLQueryItem {
                $components = preg_split(sprintf("/%s/", preg_quote("=", "/")), $pair, -1, PREG_SPLIT_NO_EMPTY);
                $name = $components[0];
                $value = (count($components) === 2) ? urldecode($components[1]) : null;
                if ($value) {
                    $value = htmlspecialchars($value, ENT_QUOTES);
                }
                return new URLQueryItem($name, $value);
            });
        } else {
            return $this->valueForUndefinedKey($name);
        }
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name == "pass") {
            $this->password = $value;
        } elseif ($name == "queryItems") {
            if ($value === null) {
                $this->query = null;
            } else {
                $this->query = http_build_query($value->flatMap(fn(URLQueryItem $queryItem): array => [$queryItem->name => $queryItem->value])->toArray());
            }
        } else {
            $this->setValueForUndefinedKey($value, $name);
        }
    }

    /**
     * Returns a URL based on the component settings and relative to a given base URL.
     *
     * If the URLComponents has an authority component (user, password, host or port) and a path component, then the path must either begin with “/” or be an empty string.
     * If the URLComponents does not have an authority component (user, password, host or port) and has a path component, the path component must not start with “//”. If those requirements are not met, nil is returned.
     */
    public function urlRelativeTo(?URL $baseURL): ?URL
    {
        if ($string = $this->string) {
            return new URL($string, $baseURL);
        }
        return null;
    }
}
