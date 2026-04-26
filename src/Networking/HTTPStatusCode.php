<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/**
 * This class defines constants for standard HTTP status codes.
 */
final class HTTPStatusCode
{
    final const int continue = 100;
    final const int switchingProtocols = 101;
    final const int ok = 200;
    final const int created = 201;
    final const int accepted = 202;
    final const int nonAuthoritativeInformation = 203;
    final const int noContent = 204;
    final const int resetContent = 205;
    final const int partialContent = 206;
    final const int multipleChoices = 300;
    final const int movedPermanently = 301;
    final const int found = 302;
    final const int seeOther = 303;
    final const int notModified = 304;
    final const int useProxy = 305;
    final const int switchProxy = 306;
    final const int temporaryRedirect = 307;
    final const int permanentRedirect = 308;
    final const int badRequest = 400;
    final const int unauthorized = 401;
    final const int paymentRequired = 402;
    final const int forbidden = 403;
    final const int notFound = 404;
    final const int methodNotAllowed = 405;
    final const int unacceptable = 406;
    final const int proxyAuthenticationRequired = 407;
    final const int requestTimeout = 408;
    final const int conflict = 409;
    final const int gone = 410;
    final const int lengthRequired = 411;
    final const int preconditionFailed = 412;
    final const int requestTooLarge = 413;
    final const int requestedURLTooLong = 414;
    final const int unsupportedMediaType = 415;
    final const int requestedRangeNotSatisfiable = 416;
    final const int expectationFailed = 417;
    final const int tooManyRequests = 429;
    final const int internalServerError = 500;
    final const int unimplemented = 501;
    final const int badGateway = 502;
    final const int serviceUnavailable = 503;
    final const int gatewayTimeout = 504;
    final const int unsupportedVersion = 505;
}
