<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

/**
 * Class HTTPStatusCode
 * Constants to use with {@see HTTPURLResponse::statusCode()}.
 * @package Sabatier\Foundation
 */
class HTTPStatusCode
{
    const continue = 100;
    const switchingProtocols = 101;
    const ok = 200;
    const created = 201;
    const accepted = 202;
    const nonAuthoritativeInformation = 203;
    const noContent = 204;
    const resetContent = 205;
    const partialContent = 206;
    const multipleChoices = 300;
    const movedPermanently = 301;
    const found = 302;
    const seeOther = 303;
    const notModified = 304;
    const temporaryRedirect = 307;
    const badRequest = 400;
    const unauthorized = 401;
    const paymentRequired = 402;
    const forbidden = 403;
    const notFound = 404;
    const methodNotAllowed = 405;
    const unacceptable = 406;
    const proxyAuthenticationRequired = 407;
    const requestTimeout = 408;
    const conflict = 409;
    const lengthRequired = 411;
    const preconditionFailed = 412;
    const requestTooLarge = 413;
    const requestedURLTooLong = 414;
    const unsupportedMediaType = 415;
    const requestedRangeNotSatisfiable = 416;
    const expectationFailed = 417;
    const internalServerError = 500;
    const unimplemented = 501;
    const badGateway = 502;
    const serviceUnavailable = 503;
    const gatewayTimeout = 504;
    const unsupportedVersion = 505;
}
