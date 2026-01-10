<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 26/06/20
 * Time: 22:40
 */

namespace Sabatier\Foundation\Predicates;

use Countable;
use DateTime;
use Exception;
use JetBrains\PhpStorm\Language;
use JetBrains\PhpStorm\Pure;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Nil;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SystemRandomNumberGenerator;
use Sabatier\Foundation\UUID;
use Stringable;
use function Sabatier\Foundation\canonical;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\is_equal;
use function Sabatier\Foundation\pn;
use function Sabatier\Foundation\substring_to_index;
use const Sabatier\Foundation\NotFound;

/** @internal */
final class PredicateUtilities
{
    /** @var Set<string>|null $reservedWords */
    private static ?Set $reservedWords = null;

    /**
     * @return Set<string>
     */
    private static function reservedWords(): Set
    {
        self::$reservedWords ??= new Set(["all", "and", "any", "anykey", "apply", "beginswith", "between", "cast", "contains", "endswith", "false", "falsepredicate", "first", "function", "in", "intersection", "last", "like", "matches", "minus", "null", "no", "none", "not", "null", "or", "self", "size", "some", "subquery", "tokenmatches", "true", "truepredicate", "union", "yes"]);
        return self::$reservedWords;
    }

    public static function isReserved(string $word): bool
    {
        return self::reservedWords()->containsElement(self::lowercase($word));
    }

    public static function average(ArrayClass|Set $values): Number
    {
        if ($values->isEmpty) {
            return new Number(0);
        }
        return new Number($values->sum() / $values->count());
    }

    public static function avg(ArrayClass|Set $values): Number
    {
        return self::average($values);
    }

    public static function sum(ArrayClass|Set $values): Number
    {
        return new Number($values->sum());
    }

    public static function count(Countable|Stringable $value): Number
    {
        return new Number($value instanceof Countable ? $value->count() : strlen((string)$value));
    }

    public static function min(ArrayClass|Set $values): ?Number
    {
        $value = $values->min();
        if ($value === null) {
            return null;
        }
        if ($value instanceof Number) {
            return $value;
        }
        return new Number($value);
    }

    public static function max(ArrayClass|Set $values): ?Number
    {
        $value = $values->max();
        if ($value === null) {
            return null;
        }
        if ($value instanceof Number) {
            return $value;
        }
        return new Number($value);
    }

    public static function median(ArrayClass|Set $values): Number
    {
        if ($values->isEmpty) {
            return new Number(0);
        }
        $values = $values->sort(fn(Number|int|float $e1, Number|int|float $e2): int => pn($e1) <=> pn($e2));
        $count = $values->count;
        if ($count % 2 === 0) {
            return new Number((pn($values[(int)($count / 2)]) + pn($values[(int)($count / 2) - 1])) / 2);
        }
        return new Number($values[(int)(($count - 1) / 2)]);
    }

    public static function mode(ArrayClass|Set $values): Number
    {
        if (!$values->isEmpty) {
            /** @var Dictionary<int> $occurrences */
            $occurrences = $values->reduce(new Dictionary(), function (Dictionary $result, Number|int|float $element): Dictionary {
                /** @psalm-suppress NullOperand */
                $result[(string)$element] += 1;
                return $result;
            });
            $max = $occurrences->max();
            if (($max !== null) && ($mode = $occurrences->firstIndex(fn(int|float $element): bool => $element === pn($max)))) {
                return new Number($mode);
            }
        }
        return new Number(0);
    }

    public static function stddev(ArrayClass|Set $values): Number
    {
        if ($values->isEmpty) {
            return new Number(0);
        }
        $count = $values->count;
        $avg = abs($values->sum()) / $count;
        $sum = $values->map(fn(Number|int|float $element): int|float => (pn($element) - $avg) ** 2)->sum();
        return $sum ? new Number(sqrt($sum / ($count - 1))) : new Number($sum);
    }

    public static function addTo(Number|int|float $addend1, Number|int|float $addend2): Number
    {
        return new Number(pn($addend1) + pn($addend2));
    }

    public static function fromSubtract(Number|int|float $minuendo, Number|int|float $subtracting): Number
    {
        return new Number(pn($minuendo) - pn($subtracting));
    }

    public static function multiplyBy(Number|int|float $factor1, Number|int|float $factor2): Number
    {
        return new Number(pn($factor1) * pn($factor2));
    }

    public static function divideBy(Number|int|float $dividend, Number|int|float $divisor): Number
    {
        return new Number(pn($dividend) / pn($divisor));
    }

    public static function modulusBy(Number|int|float $dividend, Number|int|float $divisor): Number
    {
        return new Number(fmod(pn($dividend), pn($divisor)));
    }

    public static function sqrt(Number|float $arg): Number
    {
        return new Number(sqrt(pn($arg)));
    }

    public static function ln(Number|float $arg): Number
    {
        return new Number(log(pn($arg)));
    }

    public static function log(Number|float $arg, Number|float $base = 10): Number
    {
        return new Number(log(pn($arg), pn($base)));
    }

    public static function raiseToPower(Number|int|float $base, Number|int|float $exp): Number
    {
        return new Number(pn($base) ** pn($exp));
    }

    public static function exp(Number|float $arg): Number
    {
        return new Number(exp(pn($arg)));
    }

    public static function ceiling(Number|float $value): Number
    {
        return new Number(ceil(pn($value)));
    }

    public static function abs(Number|int|float $number): Number
    {
        return new Number(abs(pn($number)));
    }

    public static function trunc(Number|int|float $number): Number
    {
        return new Number((int)(pn($number) * 1e2 / 1e2));
    }

    public static function random(Number|int $max = NotFound): Number
    {
        return new Number(new SystemRandomNumberGenerator()->next((int)pn($max)));
    }

    public static function cast(mixed $value, ?string $type = null): mixed
    {
        return match ($type) {
            null => $value,
            "string" => human_readable_value($value),
            "int", "integer" => new Number($value)->intValue,
            "float", "double" => new Number($value)->floatValue,
            "bool", "boolean" => new Number($value)->boolValue,
            Date::class => new Date(new Number($value)->floatValue),
            Number::class => new Number($value),
            default => fatal_error(sprintf("Do not know how to cast %s to type %s", human_readable_value($value), $type))
        };
    }

    public static function now(): Date
    {
        return new Date();
    }

    public static function year(?Date $date): ?Number
    {
        if (!$date instanceof Date) {
            return null;
        }
        return new Number($date->format("Y"));
    }

    public static function month(?Date $date): ?Number
    {
        if (!$date instanceof Date) {
            return null;
        }
        return new Number($date->format("n"));
    }

    public static function week(?Date $date): ?Number
    {
        if (!$date instanceof Date) {
            return null;
        }
        return new Number($date->format("W"));
    }

    public static function day(?Date $date): ?Number
    {
        if (!$date instanceof Date) {
            return null;
        }
        return new Number($date->format("j"));
    }

    public static function hour(?Date $date): ?Number
    {
        if (!$date instanceof Date) {
            return null;
        }
        return new Number($date->format("G"));
    }

    public static function minute(?Date $date): ?Number
    {
        if (!$date instanceof Date) {
            return null;
        }
        return new Number($date->format("i"));
    }

    public static function second(?Date $date): ?Number
    {
        if (!$date instanceof Date) {
            return null;
        }
        return new Number($date->format("s"));
    }

    public static function date(?Date $date): ?string
    {
        return self::dateFormat($date, "Y-m-d");
    }

    public static function currentDate(): ?string
    {
        return self::dateFormat(new Date(), "Y-m-d");
    }

    public static function dateFormat(?Date $date, string $format = "Y-m-d H:i:s"): ?string
    {
        if (!$date instanceof Date) {
            return null;
        }
        return $date->format($format);
    }

    /**
     * @throws Exception
     */
    public static function dateDiff(string $unit, Date|string|null $d1, Date|string|null $d2): Number
    {
        $unit = match ($unit) {
            "YEAR" => "y",
            "MONTH" => "m",
            "DAY" => "d",
            "HOUR" => "h",
            "MINUTE" => "i",
            "SECOND" => "s",
            "MICROSECOND" => "f",
            default => $unit
        };
        return new Number(new DateTime((string)$d1)->diff(new DateTime((string)$d2))->$unit ?? 0);
    }

    public static function floor(Number|float $value): Number
    {
        return new Number(floor(pn($value)));
    }

    #[Pure]
    public static function uppercase(string $string): string
    {
        return strtoupper($string);
    }

    #[Pure]
    public static function lowercase(string $string): string
    {
        return strtolower($string);
    }

    public static function canonical(string $string): string
    {
        return canonical($string);
    }

    public static function concat(string $separator = "", ArrayClass $arguments = new ArrayClass()): string
    {
        return $arguments->join($separator);
    }

    public static function substring(string $string, int $index): string
    {
        return substring_to_index($string, $index);
    }

    public static function replace(string $string, string $search, string $replace): string
    {
        return str_replace($search, $replace, $string);
    }

    public static function length(string $string): int
    {
        return strlen($string);
    }

    public static function trim(string $string): string
    {
        return trim($string);
    }

    /**
     * @param string $subject
     * @param non-empty-string $pattern
     * @param string $replace
     * @return string
     */
    public static function regexpReplace(string $subject, #[Language("RegExp")] string $pattern, string $replace): string
    {
        return (string)preg_replace($pattern, $replace, $subject);
    }

    public static function uuid(): UUID
    {
        return new UUID();
    }

    public static function isNull(mixed $value): bool
    {
        return Nil::nil()->isEqual($value);
    }

    public static function ifNull(mixed $value, mixed $default): mixed
    {
        return self::isNull($value) ? $default : $value;
    }

    public static function nullIf(mixed $a, mixed $b): mixed
    {
        return is_equal($a, $b) ? null : $a;
    }

    public static function bitwiseAndWith(int $n1, int $n2): Number
    {
        return new Number($n1 & $n2);
    }

    public static function bitwiseOrWith(int $n1, int $n2): Number
    {
        return new Number($n1 | $n2);
    }

    public static function bitwiseXorWith(int $n1, int $n2): Number
    {
        return new Number($n1 ^ $n2);
    }

    public static function leftshiftBy(int $n1, int $n2): Number
    {
        return new Number($n1 << $n2);
    }

    public static function rightshiftBy(int $n1, int $n2): Number
    {
        return new Number($n1 >> $n2);
    }

    public static function onesComplement(int $number): Number
    {
        return new Number(((1 << ((int)(log($number) / log(2)) + 1)) - 1) ^ $number);
    }

    public static function chs(int $number): Number
    {
        return new Number(-$number);
    }

    public static function index(ArrayClass $values, mixed $index): mixed
    {
        $index = pn($index);
        if (is_float($index)) {
            $index = (int)$index;
        }
        return $values[$index];
    }

    public static function indexFirst(ArrayClass $values): mixed
    {
        return $values->first;
    }

    public static function indexLast(ArrayClass $values): mixed
    {
        return $values->last;
    }

    public static function indexSize(ArrayClass $values): Number
    {
        return new Number($values->count);
    }
}
