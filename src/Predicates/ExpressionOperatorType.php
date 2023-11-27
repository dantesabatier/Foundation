<?php

namespace Sabatier\Foundation\Predicates;

use Sabatier\Foundation\CompareOptions;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\string_is_equal;

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
    case isNull = 996;
    case ifNull = 997;
    case nullIf = 998;
    case cast = 1000;
    case chs = 1001;

    public function symbol(): string
    {
        return match ($this) {
            self::addTo => ExpressionOperatorSymbol::addition,
            self::fromSubtract => ExpressionOperatorSymbol::subtraction,
            self::multiplyBy => ExpressionOperatorSymbol::multiplication,
            self::divideBy => ExpressionOperatorSymbol::division,
            self::modulusBy => ExpressionOperatorSymbol::modulo,
            self::raiseToPower => ExpressionOperatorSymbol::raiseToPower,
            self::bitwiseAndWith => ExpressionOperatorSymbol::bitwiseAnd,
            self::bitwiseOrWith => ExpressionOperatorSymbol::bitwiseOr,
            self::bitwiseXorWith => ExpressionOperatorSymbol::bitwiseXor,
            self::leftshiftBy => ExpressionOperatorSymbol::shiftLeft,
            self::rightshiftBy => ExpressionOperatorSymbol::shiftRight,
            default => $this->name
        };
    }

    public static function fromFunctionName(string $functionName): ExpressionOperatorType
    {
        if (string_is_equal($functionName, "average:", CompareOptions::caseInsensitive) || string_is_equal($functionName, "avg:", CompareOptions::caseInsensitive)) {
            return self::average;
        } elseif (string_is_equal($functionName, "sum:", CompareOptions::caseInsensitive)) {
            return self::sum;
        } elseif (string_is_equal($functionName, "count:", CompareOptions::caseInsensitive)) {
            return self::count;
        } elseif (string_is_equal($functionName, "min:", CompareOptions::caseInsensitive)) {
            return self::min;
        } elseif (string_is_equal($functionName, "max:", CompareOptions::caseInsensitive)) {
            return self::max;
        } elseif (string_is_equal($functionName, "median:", CompareOptions::caseInsensitive)) {
            return self::median;
        } elseif (string_is_equal($functionName, "mode:", CompareOptions::caseInsensitive)) {
            return self::mode;
        } elseif (string_is_equal($functionName, "stddev:", CompareOptions::caseInsensitive)) {
            return self::stddev;
        } elseif (string_is_equal($functionName, "add:to:", CompareOptions::caseInsensitive)) {
            return self::addTo;
        } elseif (string_is_equal($functionName, "from:subtract:", CompareOptions::caseInsensitive)) {
            return self::fromSubtract;
        } elseif (string_is_equal($functionName, "multiply:by:", CompareOptions::caseInsensitive)) {
            return self::multiplyBy;
        } elseif (string_is_equal($functionName, "divide:by:", CompareOptions::caseInsensitive)) {
            return self::divideBy;
        } elseif (string_is_equal($functionName, "modulus:by:", CompareOptions::caseInsensitive)) {
            return self::modulusBy;
        } elseif (string_is_equal($functionName, "sqrt:", CompareOptions::caseInsensitive)) {
            return self::sqrt;
        } elseif (string_is_equal($functionName, "ln:", CompareOptions::caseInsensitive)) {
            return self::ln;
        } elseif (string_is_equal($functionName, "log:", CompareOptions::caseInsensitive)) {
            return self::log;
        } elseif (string_is_equal($functionName, "raise:toPower:", CompareOptions::caseInsensitive)) {
            return self::raiseToPower;
        } elseif (string_is_equal($functionName, "exp:", CompareOptions::caseInsensitive)) {
            return self::exp;
        } elseif (string_is_equal($functionName, "ceiling:", CompareOptions::caseInsensitive)) {
            return self::ceiling;
        } elseif (string_is_equal($functionName, "abs:", CompareOptions::caseInsensitive)) {
            return self::abs;
        } elseif (string_is_equal($functionName, "trunc:", CompareOptions::caseInsensitive)) {
            return self::trunc;
        } elseif (string_is_equal($functionName, "random:", CompareOptions::caseInsensitive)) {
            return self::random;
        } elseif (string_is_equal($functionName, "now:", CompareOptions::caseInsensitive)) {
            return self::now;
        } elseif (string_is_equal($functionName, "floor:", CompareOptions::caseInsensitive)) {
            return self::floor;
        } elseif (string_is_equal($functionName, "uppercase:", CompareOptions::caseInsensitive)) {
            return self::uppercase;
        } elseif (string_is_equal($functionName, "lowercase:", CompareOptions::caseInsensitive)) {
            return self::lowercase;
        } elseif (string_is_equal($functionName, "canonical:", CompareOptions::caseInsensitive)) {
            return self::canonical;
        } elseif (string_is_equal($functionName, "bitwiseAnd:with:", CompareOptions::caseInsensitive)) {
            return self::bitwiseAndWith;
        } elseif (string_is_equal($functionName, "bitwiseOr:with:", CompareOptions::caseInsensitive)) {
            return self::bitwiseOrWith;
        } elseif (string_is_equal($functionName, "bitwiseXor:with:", CompareOptions::caseInsensitive)) {
            return self::bitwiseXorWith;
        } elseif (string_is_equal($functionName, "leftshift:by:", CompareOptions::caseInsensitive)) {
            return self::leftshiftBy;
        } elseif (string_is_equal($functionName, "rightshift:by:", CompareOptions::caseInsensitive)) {
            return self::rightshiftBy;
        } elseif (string_is_equal($functionName, "onesComplement:", CompareOptions::caseInsensitive)) {
            return self::onesComplement;
        } elseif (string_is_equal($functionName, "index:", CompareOptions::caseInsensitive)) {
            return self::index;
        } elseif (string_is_equal($functionName, "first:", CompareOptions::caseInsensitive)) {
            return self::indexFirst;
        } elseif (string_is_equal($functionName, "last:", CompareOptions::caseInsensitive)) {
            return self::indexLast;
        } elseif (string_is_equal($functionName, "size:", CompareOptions::caseInsensitive)) {
            return self::indexSize;
        } elseif (string_is_equal($functionName, "cast:", CompareOptions::caseInsensitive)) {
            return self::cast;
        } elseif (string_is_equal($functionName, "chs:", CompareOptions::caseInsensitive)) {
            return self::chs;
        } elseif (string_is_equal($functionName, "year:", CompareOptions::caseInsensitive)) {
            return self::year;
        } elseif (string_is_equal($functionName, "month:", CompareOptions::caseInsensitive)) {
            return self::month;
        } elseif (string_is_equal($functionName, "week:", CompareOptions::caseInsensitive)) {
            return self::week;
        } elseif (string_is_equal($functionName, "day:", CompareOptions::caseInsensitive)) {
            return self::day;
        } elseif (string_is_equal($functionName, "hour:", CompareOptions::caseInsensitive)) {
            return self::hour;
        } elseif (string_is_equal($functionName, "minute:", CompareOptions::caseInsensitive)) {
            return self::minute;
        } elseif (string_is_equal($functionName, "second:", CompareOptions::caseInsensitive)) {
            return self::second;
        } elseif (string_is_equal($functionName, "uuid:", CompareOptions::caseInsensitive)) {
            return self::uuid;
        } elseif (string_is_equal($functionName, "concat:", CompareOptions::caseInsensitive)) {
            return self::concat;
        } elseif (string_is_equal($functionName, "isNull:", CompareOptions::caseInsensitive)) {
            return self::isNull;
        } elseif (string_is_equal($functionName, "ifNull:", CompareOptions::caseInsensitive)) {
            return self::ifNull;
        } elseif (string_is_equal($functionName, "nullIf:", CompareOptions::caseInsensitive)) {
            return self::nullIf;
        } else {
            fatal_error(sprintf("%s unable to parse selector name \"%s\" into supported method", self::class, $functionName));
        }
    }

    public function functionName(): string
    {
        return match ($this) {
            self::addTo => "add:to:",
            self::fromSubtract => "from:subtract:",
            self::multiplyBy => "multiply:by:",
            self::divideBy => "divide:by:",
            self::modulusBy => "modulus:by:",
            self::raiseToPower => "raise:toPower:",
            self::bitwiseAndWith => "bitwiseAnd:with:",
            self::bitwiseOrWith => "bitwiseOr:with:",
            self::bitwiseXorWith => "bitwiseXor:with:",
            self::leftshiftBy => "leftshift:by:",
            self::rightshiftBy => "rightshift:by:",
            self::indexFirst => "first:",
            self::indexLast => "last:",
            self::indexSize => "size:",
            default => "$this->name:",
        };
    }

    public function isDeterministic(): bool
    {
        return match ($this) {
            self::average, self::sum, self::count, self::min, self::max, self::stddev, self::sqrt, self::ln, self::log, self::raiseToPower, self::exp, self::ceiling, self::abs, self::trunc, self::floor, self::uppercase, self::lowercase, self::year, self::month, self::week, self::day, self::hour, self::minute, self::second, self::concat, self::isNull, self::ifNull, self::nullIf => true,
            default => false,
        };
    }
}
