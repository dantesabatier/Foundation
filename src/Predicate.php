<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;

/**
 * Class Predicate
 * A definition of logical conditions used to constrain a search either for a fetch or for in-memory filtering.
 * @package Sabatier\Foundation
 */
class Predicate extends ObjectClass
{
    /** @internal */
    public static int $debugLevel = 0;

    /**
     * Initializes a predicate by substituting the values in a given array into a format string and parsing the result.
     * @param string $format The format string for the new predicate.
     * @param ArrayClass<mixed> $arguments The arguments to substitute into format. Values are substituted in the order they appear in the array.
     * @return Predicate|null A new predicate by substituting the values in arguments into format, and parsing the result.
     */
    public static function format(string $format, ArrayClass $arguments = new ArrayClass()): ?Predicate
    {
        return (new PredicateScanner($format, $arguments))->predicate();
    }

    /**
     * Creates and returns a predicate that always evaluates to a given Boolean value.
     * @param bool $value The Boolean value to which the new predicate should evaluate.
     * @return Predicate A predicate that always evaluates to value.
     */
    #[Pure]
    public static function value(bool $value): Predicate
    {
        return $value ? new TruePredicate() : new FalsePredicate();
    }

    /**
     * Initializes a predicate that evaluates using a specified block object and bindings dictionary.
     * @param Closure(mixed, Dictionary<mixed>|null): bool $block The block is applied to the object to be evaluated.
     * @return Predicate The block returns true if the evaluatedObject evaluates to true, otherwise false.
     */
    #[Pure]
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
     * Returns a Boolean value indicating whether the specified object matches the conditions specified by the predicate after substituting in the values in a given variables dictionary.
     * @param mixed $object The object against which to evaluate the predicate.
     * @param Dictionary<mixed>|null $substitutionVariables The substitution variables dictionary.
     * The dictionary must contain key-value pairs for all variables in the predicate.
     * @return bool true if object matches the conditions specified by the predicate after substituting in the values in variables for any replacement tokens, otherwise false.
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
     * The predicate's format string.
     */
    public function predicateFormat(): string
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    /**
     * Forces a predicate that was securely decoded to allow evaluation.
     * When securely decoding Predicate objects that are encoded using SecureCoding, evaluation is disabled because it is potentially unsafe to evaluate predicates you get out of an archive.
     * Before you enable evaluation, you should validate key paths, selectors, and other details to ensure no erroneous or malicious code will be executed.
     * Once you've verified the predicate, you can enable the receiver for evaluation by calling allowEvaluation().
     */
    public function allowEvaluation(): void
    {
    }

    public function description(): string
    {
        return $this->predicateFormat();
    }
}
