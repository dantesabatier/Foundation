<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use function Sabatier\Foundation\request_concrete_implementation;

/**
 * A definition of logical conditions used to constrain a search either for a fetch or for in-memory filtering.
 */
class Predicate extends ObjectClass
{
    /** @internal */
    public static bool $debugDefault = false;
    /** @var Closure(string): void|null $debugHandler Where a debug line goes. When null it reaches error_log(), which is the server log; assign a closure to send the trace somewhere a test or a console can read it. */
    public static ?Closure $debugHandler = null;
    /** @var int $debugDepth How deep the evaluation currently is, so a line can be indented to show where it sits in a compound predicate. */
    private static int $debugDepth = 0;
    /** @var string The predicate's format string. */
    public string $predicateFormat {
        get => request_concrete_implementation($this, __PROPERTY__);
    }
    #[Override]
    public string $description {
        get => $this->predicateFormat;
    }
    #[Override]
    public string $canonicalDescription {
        get => $this->predicateFormat;
    }

    /**
     * Initializes a predicate by substituting the values in a given array into a format string and parsing the result.
     * @param string $format The format string for the new predicate.
     * @param ArrayClass $arguments The arguments to substitute into format. Values are substituted in the order they appear in the array.
     * @return Predicate|null A new predicate by substituting the values in arguments into format and parsing the result.
     */
    public static function format(string $format, ArrayClass $arguments = new ArrayClass()): ?Predicate
    {
        return new PredicateScanner($format, $arguments)->predicate;
    }

    /**
     * Emits one line of the evaluation trace, indented to the depth it was reached at.
     *
     * @param string $message The line to emit, without the framework prefix or indentation.
     * @internal
     */
    public static function debug(string $message): void
    {
        $line = sprintf("Foundation: %s%s", str_repeat("  ", self::$debugDepth), $message);
        if ($handler = self::$debugHandler) {
            $handler($line);
            return;
        }
        error_log($line);
    }

    /**
     * Runs a step of an evaluation one level deeper, so anything it traces is indented under the line that announced it.
     *
     * @param Closure(): mixed $step The evaluation to run.
     * @return mixed Whatever the step produced.
     * @internal
     */
    public static function debugNested(Closure $step): mixed
    {
        if (!self::$debugDefault) {
            return $step();
        }
        self::$debugDepth++;
        try {
            return $step();
        } finally {
            self::$debugDepth--;
        }
    }

    /**
     * Creates and returns a predicate that always evaluates to a given Boolean value.
     * @param bool $value The Boolean value to which the new predicate should evaluate.
     * @return Predicate A predicate that always evaluates to value.
     */
    public static function value(bool $value): Predicate
    {
        return $value ? new TruePredicate() : new FalsePredicate();
    }


    public static function block(Closure $block): Predicate
    {
        return new BlockPredicate($block);
    }

    /**
     * Returns a copy of the predicate with the predicate's variables substituted by values specified in a given substitution variables dictionary.
     * @param Dictionary<mixed> $variables The substitution variables dictionary.
     * The dictionary must contain key-value pairs for all variables in the receiver.
     * @return Predicate A copy of the receiver with the predicate's variables substituted by values specified in variables.
     * The predicate itself is not modified by this method, so you can reuse it for any number of substitutions.
     */
    public function withSubstitutionVariables(Dictionary $variables): Predicate
    {
        return $this;
    }

    /**
     * Initializes a predicate with a metadata query string.
     * @param string $queryString A metadata query string.
     */
    public function fromMetadataQueryString(/** @noinspection PhpUnusedParameterInspection */ string $queryString): ?Predicate
    {
        return null;
    }

    /**
     * Returns a Boolean value indicating whether the specified object matches the conditions specified by the predicate after substituting in the values in a given Variables dictionary.
     * @param mixed $object The object against which to evaluate the predicate.
     * @param Dictionary<mixed>|null $substitutionVariables The substitution variables dictionary.
     * The dictionary must contain key-value pairs for all variables in the predicate.
     * @return bool true if $object matches the conditions specified by the predicate after substituting in the values in variables for any replacement tokens, otherwise false.
     */
    public function evaluate(mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    /** @internal */
    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    /**
     * Does nothing. The method is kept for signature compatibility with the framework this ports, where a securely decoded predicate arrives with evaluation disabled and this re-enables it once the caller has vetted it.
     *
     * There is no such disabled state here: a decoded predicate evaluates like any other, so calling this changes nothing and skipping it withholds nothing.
     * Evaluating a predicate that came out of an archive runs the key paths and selectors the archive named, so validate those before evaluating, and decode the archive with an explicit class list — see {@see \Sabatier\Foundation\KeyedUnarchiver::unarchiveTopLevelObjectWithData()}.
     */
    public function allowEvaluation(): void
    {
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->predicateFormat;
    }
}
