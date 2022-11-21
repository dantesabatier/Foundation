<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

/**
 * A specific point in time, independent of any calendar or time zone.
 */
class Date extends ObjectClass
{
    /** @var float The number of seconds from 1 January 1970 to the reference date, 1 January 2001. */
    final public const timeIntervalBetween1970AndReferenceDate = kCFAbsoluteTimeIntervalSince1970;
    /** @var float The time interval between the date value and the current date and time. If the date is earlier than the current date and time, this property's value is negative. */
    public readonly float $timeIntervalSinceNow;
    /** @var float The interval between the date value and 00:00:00 UTC on 1 January 1970. This property's value is negative if the date object is earlier than 00:00:00 UTC on 1 January 1970. */
    public readonly float $timeIntervalSince1970;
    /** @var float The interval between the date value and 00:00:00 UTC on 1 January 2001. This property's value is negative if the date object is earlier than the system's absolute reference date (00:00:00 UTC on 1 January 2001). */
    public float $timeIntervalSinceReferenceDate;

    public function __construct(?float $time = null)
    {
        $this->timeIntervalSinceReferenceDate = $time ?? absolute_time_get_current();
        $this->timeIntervalSinceNow = $this->timeIntervalSinceReferenceDate - absolute_time_get_current();
        $this->timeIntervalSince1970 = $this->timeIntervalSinceReferenceDate - self::timeIntervalBetween1970AndReferenceDate;
    }

    #[Pure]
    #[ArrayShape(['timeIntervalSinceReferenceDate' => "float", 'timeIntervalSinceNow' => "float", 'timeIntervalSince1970' => "float"])]
    public function __serialize(): array
    {
        return ['timeIntervalSinceReferenceDate' => $this->timeIntervalSinceReferenceDate];
    }

    public function __unserialize(array $data): void
    {
        $this->timeIntervalSinceReferenceDate = $data['timeIntervalSinceReferenceDate'];
        $this->timeIntervalSinceNow = $data['timeIntervalSinceNow'];
        $this->timeIntervalSince1970 = $data['timeIntervalSince1970'];
    }

    /**
     * Creates a date value initialized relative to the current date and time by a given number of seconds.
     *
     * @param float $timeInterval The number of seconds from the current date and time for the new date. Use a negative value to specify a date before the current date.
     * @return Date A Date object set to seconds from the current date and time.
     */
    public static function dateWithTimeIntervalSinceNow(float $timeInterval): Date
    {
        return Date::dateWithTimeIntervalSinceReferenceDate($timeInterval + absolute_time_get_current());
    }

    /**
     * Creates a date value initialized relative to another given date by a given number of seconds.
     *
     * @param float $timeInterval The number of seconds to add to date. A negative value means the receiver will be earlier than date.
     * @param Date $date The reference date.
     * @return Date A Date object set to seconds from date.
     */
    public static function dateWithTimeIntervalSinceDate(float $timeInterval, Date $date): Date
    {
        return Date::dateWithTimeIntervalSinceReferenceDate($date->timeIntervalSinceReferenceDate + $timeInterval);
    }

    /**
     * Creates and returns a date object set to the given number of seconds from 00:00:00 UTC on 1 January 1970.
     *
     * This method is useful for creating Date objects from time_t values returned by BSD system functions.
     * @param float $timeInterval The number of seconds from the reference date (00:00:00 UTC on 1 January 1970) for the new date. Use a negative argument to specify a date and time before the reference date.
     * @return Date A Date object set to seconds from the reference date.
     */
    public static function dateWithTimeIntervalSince1970(float $timeInterval): Date
    {
        return Date::dateWithTimeIntervalSinceReferenceDate($timeInterval + Date::timeIntervalBetween1970AndReferenceDate);
    }

    /**
     * Creates and returns a date object set to a given number of seconds from 00:00:00 UTC on 1 January 2001.
     *
     * @param float $timeInterval The number of seconds from the absolute reference date (00:00:00 UTC on 1 January 2001) for the new date. Use a negative argument to specify a date and time before the reference date.
     * @return Date A Date object set to seconds from the absolute reference date.
     */
    public static function dateWithTimeIntervalSinceReferenceDate(float $timeInterval): Date
    {
        return new Date($timeInterval);
    }

    /**
     * A date value representing a date in the distant future.
     * The distant future is in terms of centuries.
     */
    public static function distantFuture(): Date
    {
        return Date::dateWithTimeIntervalSinceReferenceDate(63_113_904_000.0);
    }

    /**
     * A date value representing a date in the distant past.
     * The distant past is in terms of centuries.
     */
    public static function distantPast(): Date
    {
        return Date::dateWithTimeIntervalSinceReferenceDate(-63_114_076_800.0);
    }

    public function compare(mixed $other): ComparisonResult
    {
        assert($other instanceof Date);
        return ComparisonResult::from($this->timeIntervalSinceReferenceDate <=> $other->timeIntervalSinceReferenceDate);
    }

    public function isEqual(mixed $other): bool
    {
        if ($other instanceof Date) {
            return $this->compare($other) === ComparisonResult::orderedSame;
        }
        return false;
    }

    /**
     * Returns the distance from this date to another date, specified as a time interval.
     *
     * @param Date $other Another date.
     * @return float The distance from this date to the other date.
     */
    #[Pure]
    public function distance(Date $other): float
    {
        return $other->timeIntervalSince($this);
    }

    /**
     * Returns the interval between this date and another given date.
     *
     * @param Date $date The date with which to compare to this one.
     * @return float The interval between the receiver and the another parameter. If the receiver is earlier than date, the return value is negative. If date is nil, the results are undefined.
     */
    #[Pure]
    public function timeIntervalSince(Date $date): float
    {
        return $this->timeIntervalSinceReferenceDate - $date->timeIntervalSinceReferenceDate;
    }

    /**
     * Adds a time interval to this date.
     *
     * @param float $timeInterval The value to add, in seconds.
     */
    public function addTimeInterval(float $timeInterval): void
    {
        $this->timeIntervalSinceReferenceDate += $timeInterval;
    }

    /**
     * Creates a new date value by adding a time interval to this date.
     *
     * @param float $timeInterval The value to add, in seconds.
     * @return Date A new date value calculated by adding a time interval to this date.
     */
    public function addingTimeInterval(float $timeInterval): Date
    {
        $date = new Date();
        $date->addTimeInterval($timeInterval);
        return $date;
    }

    /**
     * Returns a date offset the specified time interval from this date.
     *
     * @param float $n The time interval offset.
     * @return Date A date offset the specified time interval from this date.
     */
    public function advanced(float $n): Date
    {
        return $this->addingTimeInterval($n);
    }

    /**
     * Generates a locale-aware string representation of a date using the default date format style.
     */
    public function formatted(): string
    {
        return date('Y-m-d H:i:s', (int)$this->timeIntervalSinceReferenceDate);
    }

    public function description(): string
    {
        return $this->formatted();
    }

    public function debugDescription(): string
    {
        return sprintf("<%s %s>", static::class, $this->description());
    }

    public function jsonSerialize(): string
    {
        return $this->description();
    }
}
