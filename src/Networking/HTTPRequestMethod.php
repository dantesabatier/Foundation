<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/**
 * Defines the standard HTTP request methods.
 */
final class HTTPRequestMethod
{
    /** @var string Requests a representation of the specified resource. Requests using GET should only retrieve data. */
    const string get = "GET";
    /** @var string Asks for a response identical to that of a GET request, but without the response body. */
    const string head = "HEAD";
    /** @var string Used to submit an entity to the specified resource, often causing a change in state or side effects on the server. */
    const string post = "POST";
    /** @var string Replaces all current representations of the target resource with the request payload. */
    const string put = "PUT";
    /** @var string Deletes the specified resource. */
    const string delete = "DELETE";
    /** @var string Establishes a tunnel to the server identified by the target resource. */
    const string connect = "CONNECT";
    /** @var string Used to describe the communication options for the target resource. */
    const string options = "OPTIONS";
    /** @var string Performs a message loop-back test along the path to the target resource. */
    const string trace = "TRACE";
    /** @var string Used to apply partial modifications to a resource. */
    const string patch = "PATCH";
}
