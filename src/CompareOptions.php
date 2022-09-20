<?php

namespace Sabatier\Foundation;

/**
 * Class ComparisonOption
 * These values represent the options available to many of the string classes' search and comparison methods.
 * @package Sabatier\Foundation
 */
class CompareOptions
{
    const none = 0;
    /** @var int Case-insensitive. */
    const caseInsensitive = 1;
    /** @var int Diacritic insensitive. */
    const diacriticInsensitive = 2;
    /** @var int Indicates that the strings to be compared have been preprocessed. */
    const normalized = 4;
    /** @var int Indicates that strings to be compared using <, <=, =, =>, > should be handled in a locale aware fashion. */
    const localeSensitive = 8;
    /** @var int Search words. */
    const words = 16;
    /** @var int Strings are already quoted and will not be re-quoted using {@see preg_quote()}. */
    const quoted = 32;
}
