<?php

namespace Sabatier\Foundation;

/**
 * Defines methods of performing search operations.
 */
enum SearchMethod: int
{
    case matches = 0;
    case beginsWith = 1;
    case endsWith = 2;
    case contains = 3;
}
