<?php

namespace Sabatier\Foundation;

function url_validate(string $url): bool
{
    return preg_match("/^(https?|file|data|sql|ftps?|x-coredata):\/\//", $url) === 1;
}

function url_encode(string $url, string $endpoint, array $parameters = []): string
{
    if (!string_has_suffix($url, "/")) {
        $url .= "/";
    }
    $url .= $endpoint;
    if (!empty($parameters)) {
        $url .= "?" . http_build_query($parameters);
    }
    return $url;
}

function request_url(): string
{
    $elements = explode("?", $_SERVER["REQUEST_URI"] ?? '');
    $components = new URLComponents();
    $components->scheme = isset($_SERVER["HTTPS"]) ? "https" : "http";
    $components->host = $_SERVER["HTTP_HOST"] ?? null;
    $components->path = $elements[0] ?? null;
    $components->query = $elements[1] ?? null;
    return $components->string ?? '';
}

/**
 * @return array<string, string>
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
        if (string_has_prefix($key, "HTTP_")) {
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
            $headers["Authorization"] = "Basic " . base64_encode(sprintf("%s:%s", $_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"] ?? ''));
        } elseif (isset($_SERVER["PHP_AUTH_DIGEST"])) {
            $headers["Authorization"] = $_SERVER["PHP_AUTH_DIGEST"];
        }
    }
    return $headers;
}
