<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * The formatting context for a formatter.
 *
 * Use formatting contexts to specify where the result of formatting will appear, so that the formatter can provide the most appropriate result.
 */
enum FormatterContext: int
{
    /** An unknown formatting context. This is the default formatting context. */
    case unknown = 0;
    /** A formatting context determined automatically at runtime. A FormatterContext::dynamic context is automatically determined to be one of the following: FormatterContext::standalone, FormatterContext.beginningOfSentence, or FormatterContext::middleOfSentence. When used in combination with stringWithFormat:, the formatter returns a string proxy, formats the string using FormatterContext::unknown, determines context based on the proxy string's location, and then reformats the string accordingly. */
    case dynamic = 1;
    /** The formatting context for stand-alone usage. */
    case standalone = 2;
    /** The formatting context for a list or menu item. */
    case listItem = 3;
    /** The formatting context for the beginning of a sentence. */
    case beginningOfSentence = 4;
    /** The formatting context for the middle of a sentence. */
    case middleOfSentence = 5;
}
