<?php

namespace Sabatier\Foundation;

/**
 * Specifies the width of the unit, determining the textual representation.
 */
enum FormatterUnitStyle: int
{
    /** Specifies a short unit style. */
    case short = 0;
    /** Specifies a medium unit style. */
    case medium = 1;
    /** Specifies a long unit style. */
    case long = 2;
}