<?php

namespace Sabatier\Foundation;

/**
 * Class HTTPRequestMethod
 * Constants to use with {@see URLRequest::httpMethod()}.
 * @package Sabatier\Foundation
 */
class HTTPRequestMethod
{
    /** @var string Requests a representation of the specified resource. Requests using GET should only retrieve data. */
    const get = 'GET';
    /** @var string Asks for a response identical to that of a GET request, but without the response body. */
    const head = 'HEAD';
    /** @var string Used to submit an entity to the specified resource, often causing a change in state or side effects on the server. */
    const post = 'POST';
    /** @var string Replaces all current representations of the target resource with the request payload. */
    const put = 'PUT';
    /** @var string Deletes the specified resource. */
    const delete = 'DELETE';
    /** @var string Establishes a tunnel to the server identified by the target resource. */
    const connect = 'CONNECT';
    /** @var string Used to describe the communication options for the target resource. */
    const options = 'OPTIONS';
    /** @var string Performs a message loop-back test along the path to the target resource. */
    const trace = 'TRACE';
    /** @var string Used to apply partial modifications to a resource. */
    const patch = 'PATCH';
}
