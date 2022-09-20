<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use InvalidArgumentException;

/** @internal */
enum ExpressionOperatorType: int
{
    case average = 0;
    case sum = 1;
    case count = 2;
    case min = 3;
    case max = 4;
    case median = 5;
    case mode = 6;
    case stddev = 7;
    case addTo = 8;
    case fromSubtract = 9;
    case multiplyBy = 10;
    case divideBy = 11;
    case modulusBy = 12;
    case sqrt = 13;
    case ln = 14;
    case log = 15;
    case raiseToPower = 16;
    case exp = 17;
    case ceiling = 18;
    case abs = 19;
    case trunc = 20;
    case random = 21;
    case now = 22;
    case floor = 23;
    case uppercase = 24;
    case lowercase = 25;
    case canonical = 26;
    case bitwiseAndWith = 27;
    case bitwiseOrWith = 28;
    case bitwiseXorWith = 29;
    case leftshiftBy = 30;
    case rightshiftBy = 31;
    case onesComplement = 32;
    case index = 33;
    case indexFirst = 34;
    case indexLast = 35;
    case indexSize = 36;
    case year = 37;
    case month = 38;
    case week = 39;
    case day = 40;
    case hour = 41;
    case minute = 42;
    case second = 43;
    case uuid = 111;
    case concat = 112;
    case cast = 1000;
    case chs = 1001;

    public static function symbol(ExpressionOperatorType $operator): ?string
    {
        return match ($operator) {
            ExpressionOperatorType::addTo => ExpressionOperatorSymbol::addition,
            ExpressionOperatorType::fromSubtract => ExpressionOperatorSymbol::subtraction,
            ExpressionOperatorType::multiplyBy => ExpressionOperatorSymbol::multiplication,
            ExpressionOperatorType::divideBy => ExpressionOperatorSymbol::division,
            ExpressionOperatorType::modulusBy => ExpressionOperatorSymbol::modulo,
            ExpressionOperatorType::raiseToPower => ExpressionOperatorSymbol::raiseToPower,
            ExpressionOperatorType::bitwiseAndWith => ExpressionOperatorSymbol::bitwiseAnd,
            ExpressionOperatorType::bitwiseOrWith => ExpressionOperatorSymbol::bitwiseOr,
            ExpressionOperatorType::bitwiseXorWith => ExpressionOperatorSymbol::bitwiseXor,
            ExpressionOperatorType::leftshiftBy => ExpressionOperatorSymbol::shiftLeft,
            ExpressionOperatorType::rightshiftBy => ExpressionOperatorSymbol::shiftRight,
            default => $operator->name
        };
    }

    public static function operatorType(string $functionName): ExpressionOperatorType
    {
        if (string_is_equal($functionName, 'average:', CompareOptions::caseInsensitive) || string_is_equal($functionName, 'avg:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::average;
        } elseif (string_is_equal($functionName, 'sum:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::sum;
        } elseif (string_is_equal($functionName, 'count:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::count;
        } elseif (string_is_equal($functionName, 'min:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::min;
        } elseif (string_is_equal($functionName, 'max:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::max;
        } elseif (string_is_equal($functionName, 'median:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::median;
        } elseif (string_is_equal($functionName, 'mode:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::mode;
        } elseif (string_is_equal($functionName, 'stddev:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::stddev;
        } elseif (string_is_equal($functionName, 'add:to:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::addTo;
        } elseif (string_is_equal($functionName, 'from:subtract:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::fromSubtract;
        } elseif (string_is_equal($functionName, 'multiply:by:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::multiplyBy;
        } elseif (string_is_equal($functionName, 'divide:by:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::divideBy;
        } elseif (string_is_equal($functionName, 'modulus:by:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::modulusBy;
        } elseif (string_is_equal($functionName, 'sqrt:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::sqrt;
        } elseif (string_is_equal($functionName, 'ln:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::ln;
        } elseif (string_is_equal($functionName, 'log:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::log;
        } elseif (string_is_equal($functionName, 'raise:toPower:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::raiseToPower;
        } elseif (string_is_equal($functionName, 'exp:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::exp;
        } elseif (string_is_equal($functionName, 'ceiling:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::ceiling;
        } elseif (string_is_equal($functionName, 'abs:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::abs;
        } elseif (string_is_equal($functionName, 'trunc:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::trunc;
        } elseif (string_is_equal($functionName, 'random:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::random;
        } elseif (string_is_equal($functionName, 'now:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::now;
        } elseif (string_is_equal($functionName, 'floor:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::floor;
        } elseif (string_is_equal($functionName, 'uppercase:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::uppercase;
        } elseif (string_is_equal($functionName, 'lowercase:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::lowercase;
        } elseif (string_is_equal($functionName, 'canonical:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::canonical;
        } elseif (string_is_equal($functionName, 'bitwiseAnd:with:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::bitwiseAndWith;
        } elseif (string_is_equal($functionName, 'bitwiseOr:with:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::bitwiseOrWith;
        } elseif (string_is_equal($functionName, 'bitwiseXor:with:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::bitwiseXorWith;
        } elseif (string_is_equal($functionName, 'leftshift:by:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::leftshiftBy;
        } elseif (string_is_equal($functionName, 'rightshift:by:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::rightshiftBy;
        } elseif (string_is_equal($functionName, 'onesComplement:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::onesComplement;
        } elseif (string_is_equal($functionName, 'index:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::index;
        } elseif (string_is_equal($functionName, 'first:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::indexFirst;
        } elseif (string_is_equal($functionName, 'last:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::indexLast;
        } elseif (string_is_equal($functionName, 'size:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::indexSize;
        } elseif (string_is_equal($functionName, 'cast:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::cast;
        } elseif (string_is_equal($functionName, 'chs:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::chs;
        } elseif (string_is_equal($functionName, 'year:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::year;
        } elseif (string_is_equal($functionName, 'month:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::month;
        } elseif (string_is_equal($functionName, 'week:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::week;
        } elseif (string_is_equal($functionName, 'day:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::day;
        } elseif (string_is_equal($functionName, 'hour:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::hour;
        } elseif (string_is_equal($functionName, 'minute:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::minute;
        } elseif (string_is_equal($functionName, 'second:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::second;
        } elseif (string_is_equal($functionName, 'uuid:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::uuid;
        } elseif (string_is_equal($functionName, 'concat:', CompareOptions::caseInsensitive)) {
            return ExpressionOperatorType::concat;
        } else {
            throw new InvalidArgumentException(sprintf("%s unable to parse selector name \"%s\" into supported method", ExpressionOperatorType::class, $functionName));
        }
    }

    public static function functionName(ExpressionOperatorType $operator): string
    {
        return match ($operator) {
            ExpressionOperatorType::addTo => 'add:to:',
            ExpressionOperatorType::fromSubtract => 'from:subtract:',
            ExpressionOperatorType::multiplyBy => 'multiply:by:',
            ExpressionOperatorType::divideBy => 'divide:by:',
            ExpressionOperatorType::modulusBy => 'modulus:by:',
            ExpressionOperatorType::raiseToPower => 'raise:toPower:',
            ExpressionOperatorType::bitwiseAndWith => 'bitwiseAnd:with:',
            ExpressionOperatorType::bitwiseOrWith => 'bitwiseOr:with:',
            ExpressionOperatorType::bitwiseXorWith => 'bitwiseXor:with:',
            ExpressionOperatorType::leftshiftBy => 'leftshift:by:',
            ExpressionOperatorType::rightshiftBy => 'rightshift:by:',
            ExpressionOperatorType::indexFirst => 'first:',
            ExpressionOperatorType::indexLast => 'last:',
            ExpressionOperatorType::indexSize => 'size:',
            default => "$operator->name:",
        };
    }
}
