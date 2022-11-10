<?php

namespace Sabatier\Foundation;

/**
 * Cookie acceptance policies implemented by the {@see HTTPCookieStorage} class.
 */
enum HTTPCookieAcceptPolicy: int
{
    /** Accept all cookies. This is the default cookie accept policy. */
    case always = 0;

    /** Reject all cookies. */
    case never = 1;

    /** Accept cookies only from the main document domain. */
    case onlyFromMainDocumentDomain = 2;
}
