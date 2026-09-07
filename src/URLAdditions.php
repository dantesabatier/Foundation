<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * Checks if a string looks like a valid URL with a supported scheme.
 *
 * This is a lightweight pre-validation check to determine if a string
 * is worth parsing as a URL. It only validates that the string starts
 * with a recognized URL scheme and the syntax that scheme requires.
 *
 * This does NOT perform full URL validation - use parse_url() or your
 * URL object constructor after this check passes to validate the
 * complete URL structure.
 *
 * @param string $url The string to check
 *
 * @return bool True if the string starts with a supported scheme
 *
 * Supported schemes:
 *  - Web: http, https, ws, wss
 *  - File transfer: ftp, ftps, sftp, ssh, file
 *  - Data: data
 *  - Databases: sql, redis, mongodb, postgresql, mysql
 *  - Network: tcp, ssl
 *  - Message queues: amqp, amqps
 *  - Directory services: ldap, ldaps
 *  - Version control: git
 *  - PHP streams: php
 *  - Platform-specific: x-coredata (macOS/iOS Core Data)
 *
 * <code>
 *  is_parseable_url("https://example.com"); // true
 *  is_parseable_url("data:,hello"); // true
 *  is_parseable_url("sql://database"); // true
 *  is_parseable_url("php://input"); // true
 *  is_parseable_url("x-coredata://data"); // true
 *  is_parseable_url("not-a-url"); // false
 *  is_parseable_url("javascript:alert(1)"); // false
 * </code>
 */
function is_parseable_url(string $url): bool
{
    return string_has_prefix($url, "data:", CompareOptions::caseInsensitive) || preg_match("/^(https?|ftps?|wss?|sftp|ssh|file|sql|redis|mongodb|postgresql|mysql|ssl|tcp|amqps?|ldaps?|php|git|x-coredata):\\/\\//i", $url) === 1;
}

/**
 * Constructs a complete URL by appending an endpoint and optional query parameters to a base URL.
 *
 * @param string $url The base URL.
 * @param string $endpoint The resource endpoint to be appended to the base URL.
 * @param array<string, mixed> $parameters Optional query parameters to be appended as a query string.
 * @return string The fully assembled URL.
 */
function url_encode(string $url, string $endpoint, array $parameters = []): string
{
    if (!str_ends_with($url, "/")) {
        $url .= "/";
    }
    $url .= $endpoint;
    if ($parameters !== []) {
        $url .= "?" . http_build_query($parameters);
    }
    return $url;
}

/**
 * Constructs the full URL of the current request.
 *
 * This function builds the request URL based on the server variables available in the `$_SERVER` superglobal. It considers the scheme (HTTP or HTTPS), host, path, and query string from the request URI.
 *
 * @return string The constructed URL as a string, or an empty string if the components are not available.
 */
function request_url(): string
{
    $requestURI = $_SERVER["REQUEST_URI"] ?? null;
    $host = $_SERVER["HTTP_HOST"] ?? null;
    if (empty($requestURI) || empty($host)) {
        return "";
    }
    $elements = explode("?", $requestURI, 2);
    $components = new URLComponents();
    $https = $_SERVER["HTTPS"] ?? null;
    $components->scheme = !empty($https) && !string_is_equal($https, "off", CompareOptions::caseInsensitive) ? "https" : "http";
    $components->host = $host;
    $components->path = $elements[0];
    $components->query = $elements[1] ?? null;
    return $components->string ?? "";
}

/**
 * Retrieves all HTTP headers from the current request.
 *
 * This function collects HTTP headers present in the PHP `$_SERVER` superglobal variable. It maps certain server variables, processes headers, starting with `HTTP_`, and also includes authorization headers.
 *
 * @return array<string, string> An associative array containing the HTTP headers, where the keys are the header names and the values are the corresponding header values.
 */
function getallheaders(): array
{
    $headers = [];
    $copyServer = [
        "CONTENT_TYPE" => "Content-Type",
        "CONTENT_LENGTH" => "Content-Length",
        "CONTENT_MD5" => "Content-Md5",
    ];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, "HTTP_")) {
            $key = substring_from_index($key, 5);
            if (!isset($copyServer[$key]) || !isset($_SERVER[$key])) {
                $key = str_replace("_", " ", $key)
                        |> strtolower(...)
                        |> ucwords(...)
                        |> (fn(string $x): string => str_replace(" ", "-", $x));
                assert(is_string($value), sprintf("Invalid argument: expecting string, \"%s\" given", typeof($value)));
                $headers[$key] = $value;
            }
        } elseif (isset($copyServer[$key])) {
            assert(is_string($value), sprintf("Invalid argument: expecting string, \"%s\" given", typeof($value)));
            $headers[$copyServer[$key]] = $value;
        }
    }
    if (!isset($headers["Authorization"])) {
        if (isset($_SERVER["REDIRECT_HTTP_AUTHORIZATION"])) {
            $headers["Authorization"] = $_SERVER["REDIRECT_HTTP_AUTHORIZATION"];
        } elseif (isset($_SERVER["PHP_AUTH_USER"])) {
            $headers["Authorization"] = "Basic " . base64_encode(sprintf("%s:%s", $_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"] ?? ""));
        } elseif (isset($_SERVER["PHP_AUTH_DIGEST"])) {
            $headers["Authorization"] = $_SERVER["PHP_AUTH_DIGEST"];
        }
    }
    return $headers;
}
