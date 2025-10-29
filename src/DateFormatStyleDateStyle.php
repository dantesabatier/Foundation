<?php

namespace Sabatier\Foundation;

/**
 * Type that defines time styles varied in length or components included.
 */
enum DateFormatStyleDateStyle: int
{
    /** A date style with no date-related components represented. If both the date style and time style are set to omit, the date is represented using the default style of abbreviated. */
    case omitted = -1;
    /** A date style with all components represented. A complete date style represents the day, month, day of month, and year components in the format. For example, Saturday, October 17, 2020,for locale en_US. */
    case complete = 0;
    /** A lengthened date style with the full month, day of month, and year components represented. A long date style represents the full date without the day of the week in the format. For example, October 17, 2020. */
    case long = 1;
    /** A date style with some components abbreviated for space-constrained applications. A shortened date style that presents an abbreviated month, day of month, and year components of a date. For example, Oct 17, 2020, for locale en_US. */
    case abbreviated = 2;
    /** A date style with the month, day of month, and year components represented as numeric values. A numeric date style represents the date components using numeric values. For example, 10/17/2020, for locale en_US. */
    case numeric = 3;
}
