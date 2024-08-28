<?php

namespace Sabatier\Foundation\Networking;

/**
 * Constants to use with {@see URLRequest::httpMethod()}.
 */
class HTTPRequestMethod
{
    /** @var string Requests a representation of the specified resource. Requests using GET should only retrieve data. */
    final const string get = "GET";
    /** @var string Asks for a response identical to that of a GET request, but without the response body. */
    final const string head = "HEAD";
    /** @var string Used to submit an entity to the specified resource, often causing a change in state or side effects on the server. */
    final const string post = "POST";
    /** @var string Replaces all current representations of the target resource with the request payload. */
    final const string put = "PUT";
    /** @var string Deletes the specified resource. */
    final const string delete = "DELETE";
    /** @var string Establishes a tunnel to the server identified by the target resource. */
    final const string connect = "CONNECT";
    /** @var string Used to describe the communication options for the target resource. */
    final const string options = "OPTIONS";
    /** @var string Performs a message loop-back test along the path to the target resource. */
    final const string trace = "TRACE";
    /** @var string Used to apply partial modifications to a resource. */
    final const string patch = "PATCH";
}
