<?php

namespace Sabatier\Foundation;

/**
 * Type that defines time styles varied in length or components included.
 */
enum DateFormatStyleTimeStyle: int
{
    /** A time style with no time-related components represented. If both the date style and time style are set to omit, the time is represented using the default style of shortened. */
    case omitted = -1;
    /** A time style with all components represented. A complete time style represents the hour, minute, second, day period, and time zone components in the format. For example, 9:54:29 PM CDT, for locale en_US. */
    case complete = 0;
    /** A lengthened date style with the full month, day of month, and year components represented. A long date style represents the full date without the day of the week in the format. For example, October 17, 2020. */
    case long = 1;
    /** A time style with all components except the time zone represented. A standard time style represents the hour, minute, second, and day period components in the format. For example, 9:54:29 PM. */
    case standard = 2;
    /** A shortened time style with only the hour, minute, and day period components represented. */
    case shortened = 3;
}
