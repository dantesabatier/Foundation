<?php

namespace Sabatier\Foundation;

/**
 * Class SearchMethod
 * These constants are used by the {@see string_search()} function.
 * @package Sabatier\Foundation
 */
enum SearchMethod: int
{
    case matches = 0;
    case beginsWith = 1;
    case endsWith = 2;
    case contains = 3;
}