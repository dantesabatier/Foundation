<?php

namespace Sabatier\Foundation;

/**
 * Validates if a given URL matches allowed schemes.
 *
 * @param string $url The URL to be validated.
 * @return bool Returns true if the URL matches the allowed schemes, otherwise false.
 */
function url_validate(string $url): bool
{
    return preg_match("/^(https?|file|data|sql|ssl|tcp|ftps?|wss?|php|x-coredata):\/\//", $url) === 1;
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
    /** @var string[] $elements */
    $elements = explode("?", $_SERVER["REQUEST_URI"] ?? "");
    $components = new URLComponents();
    $components->scheme = isset($_SERVER["HTTPS"]) ? "https" : "http";
    $components->host = $_SERVER["HTTP_HOST"] ?? null;
    $components->path = $elements[0] ?? null;
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
    $copy_server = [
        "CONTENT_TYPE" => "Content-Type",
        "CONTENT_LENGTH" => "Content-Length",
        "CONTENT_MD5" => "Content-Md5",
    ];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, "HTTP_")) {
            $key = substring_from_index($key, 5);
            if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
                $key = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", $key))));
                assert(is_string($value), sprintf("Invalid argument: expecting string, \"%s\" given", typeof($value)));
                $headers[$key] = $value;
            }
        } elseif (isset($copy_server[$key])) {
            assert(is_string($value), sprintf("Invalid argument: expecting string, \"%s\" given", typeof($value)));
            $headers[$copy_server[$key]] = $value;
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
