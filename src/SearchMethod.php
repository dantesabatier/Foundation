<?php

namespace Sabatier\Foundation;

/**
 * These constants are used by the {@see string_search()} function.
 */
enum SearchMethod: int
{
    case matches = 0;
    case beginsWith = 1;
    case endsWith = 2;
    case contains = 3;
}
