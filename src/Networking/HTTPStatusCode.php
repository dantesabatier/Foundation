<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/**
 * This class defines constants for standard HTTP status codes.
 */
final class HTTPStatusCode
{
    const int continue = 100;
    const int switchingProtocols = 101;
    const int ok = 200;
    const int created = 201;
    const int accepted = 202;
    const int nonAuthoritativeInformation = 203;
    const int noContent = 204;
    const int resetContent = 205;
    const int partialContent = 206;
    const int multipleChoices = 300;
    const int movedPermanently = 301;
    const int found = 302;
    const int seeOther = 303;
    const int notModified = 304;
    const int useProxy = 305;
    const int switchProxy = 306;
    const int temporaryRedirect = 307;
    const int permanentRedirect = 308;
    const int badRequest = 400;
    const int unauthorized = 401;
    const int paymentRequired = 402;
    const int forbidden = 403;
    const int notFound = 404;
    const int methodNotAllowed = 405;
    const int unacceptable = 406;
    const int proxyAuthenticationRequired = 407;
    const int requestTimeout = 408;
    const int conflict = 409;
    const int gone = 410;
    const int lengthRequired = 411;
    const int preconditionFailed = 412;
    const int requestTooLarge = 413;
    const int requestedURLTooLong = 414;
    const int unsupportedMediaType = 415;
    const int requestedRangeNotSatisfiable = 416;
    const int expectationFailed = 417;
    const int tooManyRequests = 429;
    const int internalServerError = 500;
    const int unimplemented = 501;
    const int badGateway = 502;
    const int serviceUnavailable = 503;
    const int gatewayTimeout = 504;
    const int unsupportedVersion = 505;
}
