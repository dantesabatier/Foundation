<?php

namespace Sabatier\Foundation\Networking;

/**
 * Constants to use with {@see HTTPURLResponse::statusCode()}.
 */
class HTTPStatusCode
{
    final const continue = 100;
    final const switchingProtocols = 101;
    final const ok = 200;
    final const created = 201;
    final const accepted = 202;
    final const nonAuthoritativeInformation = 203;
    final const noContent = 204;
    final const resetContent = 205;
    final const partialContent = 206;
    final const multipleChoices = 300;
    final const movedPermanently = 301;
    final const found = 302;
    final const seeOther = 303;
    final const notModified = 304;
    final const useProxy = 305;
    final const switchProxy = 306;
    final const temporaryRedirect = 307;
    final const permanentRedirect = 308;
    final const badRequest = 400;
    final const unauthorized = 401;
    final const paymentRequired = 402;
    final const forbidden = 403;
    final const notFound = 404;
    final const methodNotAllowed = 405;
    final const unacceptable = 406;
    final const proxyAuthenticationRequired = 407;
    final const requestTimeout = 408;
    final const conflict = 409;
    final const gone = 410;
    final const lengthRequired = 411;
    final const preconditionFailed = 412;
    final const requestTooLarge = 413;
    final const requestedURLTooLong = 414;
    final const unsupportedMediaType = 415;
    final const requestedRangeNotSatisfiable = 416;
    final const expectationFailed = 417;
    final const internalServerError = 500;
    final const unimplemented = 501;
    final const badGateway = 502;
    final const serviceUnavailable = 503;
    final const gatewayTimeout = 504;
    final const unsupportedVersion = 505;
}
