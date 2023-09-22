<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;

/** @internal */
function pv(mixed $v): mixed
{
    return $v instanceof Value ? $v->value : $v;
}

/** @internal */
function pn(string|int|float|Number $n): int|float
{
    if (is_string($n) && is_numeric($n)) {
        $n = new Number($n);
    }
    $v = pv($n);
    return (is_int($v) || is_float($v)) ? $v : (int)$v;
}

/** @internal */
function string_with_options(string $string, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options): string
{
    if (($options & CompareOptions::caseInsensitive) && ($options & CompareOptions::diacriticInsensitive)) {
        if (function_exists("transliterator_transliterate")) :
            $string = transliterator_transliterate(TransliteratorDefault, $string);
            if ($string === false) {
                fatal_error(sprintf("%s() %s", __FUNCTION__, intl_get_error_message()));
            }
        endif;
        if (!($options & CompareOptions::normalized) && function_exists("normalizer_normalize")) {
            $string = normalizer_normalize($string);
            /** @psalm-suppress TypeDoesNotContainType */
            if ($string === false) {
                fatal_error(sprintf("%s() %s", __FUNCTION__, intl_get_error_message()));
            }
        }
    }
    return $string;
}
