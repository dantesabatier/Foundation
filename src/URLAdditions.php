<?php

namespace Sabatier\Foundation;

function url_validate(string $url): bool
{
    return preg_match('/^(https?|file|data|sql|ftp|x-coredata):\/\//', $url) === 1;
}

function url_encode(string $url, string $endpoint, array $parameters = []): string
{
    if (!string_has_suffix($url, '/')) {
        $url .= '/';
    }
    $url .= $endpoint;
    if (!empty($parameters)) {
        $url .= "?" . http_build_query($parameters);
    }
    return $url;
}
