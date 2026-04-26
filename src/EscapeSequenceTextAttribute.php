<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * Represents text attributes for escape sequences used in terminal formatting.
 *
 * This enum provides a collection of constants that define various text styles and attributes that can be applied when formatting terminal or console output using escape sequences.
 *
 * Each case corresponds to a specific text attribute, with its associated integer value representing the escape sequence code for that attribute.
 */
enum EscapeSequenceTextAttribute: int
{
    case normal = 0;
    case bold = 1;
    case faint = 2;
    case italic = 3;
    case underlined = 4;
    case slowBlink = 5;
    case rapidBlink = 6;
    case reverseVideo = 7;
    case conceal = 8;
    case crossedOut = 9;
    case primary = 10;
}
