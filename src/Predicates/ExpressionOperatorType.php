<?php

namespace Sabatier\Foundation\Predicates;

use function Sabatier\Foundation\fatal_error;

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
    case date = 44;
    case currentDate = 45;
    case dateFormat = 46;
    case uuid = 111;
    case concat = 112;
    case isNull = 996;
    case ifNull = 997;
    case nullIf = 998;
    case cast = 1000;
    case chs = 1001;

    public static function fromFunctionName(string $functionName): ExpressionOperatorType
    {
        return match ($functionName) {
            "average:", "avg:" => self::average,
            "sum:" => self::sum,
            "count:" => self::count,
            "min:" => self::min,
            "max:" => self::max,
            "median:" => self::median,
            "mode:" => self::mode,
            "stddev:" => self::stddev,
            "add:to:" => self::addTo,
            "from:subtract:" => self::fromSubtract,
            "multiply:by:" => self::multiplyBy,
            "divide:by:" => self::divideBy,
            "modulus:by:" => self::modulusBy,
            "sqrt:" => self::sqrt,
            "ln:" => self::ln,
            "log:" => self::log,
            "raise:toPower:" => self::raiseToPower,
            "exp:" => self::exp,
            "ceiling:" => self::ceiling,
            "abs:" => self::abs,
            "trunc:" => self::trunc,
            "random:" => self::random,
            "now:" => self::now,
            "date:" => self::date,
            "curdate:" => self::currentDate,
            "date:format:" => self::dateFormat,
            "floor:" => self::floor,
            "uppercase:" => self::uppercase,
            "lowercase:" => self::lowercase,
            "canonical:" => self::canonical,
            "bitwiseAnd:with:" => self::bitwiseAndWith,
            "bitwiseOr:with:" => self::bitwiseOrWith,
            "bitwiseXor:with:" => self::bitwiseXorWith,
            "leftshift:by:" => self::leftshiftBy,
            "rightshift:by:" => self::rightshiftBy,
            "onesComplement:" => self::onesComplement,
            "index:" => self::index,
            "first:" => self::indexFirst,
            "last:" => self::indexLast,
            "size:" => self::indexSize,
            "year:" => self::year,
            "month:" => self::month,
            "day:" => self::day,
            "hour:" => self::hour,
            "minute:" => self::minute,
            "second:" => self::second,
            "uuid:" => self::uuid,
            "concat:" => self::concat,
            "isNull:" => self::isNull,
            "ifNull:" => self::ifNull,
            "nullIf:" => self::nullIf,
            "cast:" => self::cast,
            "chs:" => self::chs,
            default => fatal_error(sprintf("%s unable to parse selector name \"%s\" into supported method", self::class, $functionName))
        };
    }
}
