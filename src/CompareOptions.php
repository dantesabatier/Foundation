<?php

namespace Sabatier\Foundation;

/**
 * These values represent the options available to many of the string classes' search and comparison methods.
 */
final class CompareOptions
{
    final const int none = 0;
    /** @var int Case-insensitive. */
    final const int caseInsensitive = 1;
    /** @var int Diacritic insensitive. */
    final const int diacriticInsensitive = 2;
    /** @var int Indicates that the strings to be compared have been preprocessed. */
    final const int normalized = 4;
    /** @var int Indicates that strings to be compared using <, <=, =, =>, > should be handled in a locale-aware fashion. */
    final const int localeSensitive = 8;
    /** @var int Search words. */
    final const int words = 16;
    /** @var int Strings are already quoted and will not be re-quoted using {@see preg_quote()}. */
    final const int quoted = 32;
}
