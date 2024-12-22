<?php

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\fatal_error;

/**
 * A specialized predicate that evaluates logical combinations of other predicates.
 *
 * Use CompoundPredicate to create an AND or OR compound predicate of zero or more other predicates, or the NOT of a single predicate. For the logical AND and OR operations:
 * An AND predicate with no subpredicates evaluates to true.
 * An OR predicate with no subpredicates evaluates to false.
 * A compound predicate with one or more subpredicates evaluates to the truth of its subpredicates.
 */
class CompoundPredicate extends Predicate
{
    public CompoundPredicateOperator $predicateOperator {
        get => match ($this->compoundPredicateType) {
            CompoundPredicateLogicalType::not => CompoundPredicateOperator::notPredicateOperator(),
            CompoundPredicateLogicalType::and => CompoundPredicateOperator::andPredicateOperator(),
            CompoundPredicateLogicalType::or => CompoundPredicateOperator::orPredicateOperator(),
        };
    }
    public string $predicateFormat {
        get {
            $type = $this->compoundPredicateType;
            $subpredicates = $this->subpredicates;
            if ($subpredicates->isEmpty) {
                /** @noinspection PhpVoidFunctionResultUsedInspection */
                return match ($type) {
                    CompoundPredicateLogicalType::and => TruePredicate::default()->predicateFormat,
                    CompoundPredicateLogicalType::or => FalsePredicate::default()->predicateFormat,
                    CompoundPredicateLogicalType::not => fatal_error("Not predicate must have exactly one subpredicate"),
                };
            }
            $arguments = $subpredicates->compactMap(function (Predicate $subpredicate): ?string {
                $precedence = $subpredicate->predicateFormat;
                if ($subpredicate instanceof CompoundPredicate) {
                    $precedence = "($precedence)";
                }
                if (empty($precedence)) {
                    return null;
                }
                return $precedence;
            });
            $separator = $this->predicateOperator->predicateFormat;
            return match ($type) {
                CompoundPredicateLogicalType::and, CompoundPredicateLogicalType::or => $arguments->join(" $separator "),
                CompoundPredicateLogicalType::not => "$separator $arguments[0]",
            };
        }
    }

    /**
     * Returns the receiver initialized to a given type using predicates from a given array.
     * @param CompoundPredicateLogicalType $compoundPredicateType The type of the new predicate (see {@see CompoundPredicateLogicalType}).
     * @param ArrayClass<covariant Predicate> $subpredicates An array of Predicate objects.
     */
    public function __construct(public readonly CompoundPredicateLogicalType $compoundPredicateType, public ArrayClass $subpredicates)
    {
    }

    /**
     * Returns a new predicate formed by AND-ing the predicates in a given array.
     * @param ArrayClass<covariant Predicate> $subpredicates An array of Predicate objects.
     * @return CompoundPredicate A new predicate formed by AND-ing the predicates specified by subpredicates.
     */
    public static function andPredicateWithSubpredicates(ArrayClass $subpredicates): CompoundPredicate
    {
        return new CompoundPredicate(CompoundPredicateLogicalType::and, $subpredicates);
    }

    /**
     * Returns a new predicate formed by NOT-ing a given predicate.
     * @param Predicate $subpredicate A predicate.
     * @return CompoundPredicate A new predicate formed by NOT-ing the predicate specified by predicate.
     */
    public static function notPredicateWithSubpredicate(Predicate $subpredicate): CompoundPredicate
    {
        return new CompoundPredicate(CompoundPredicateLogicalType::not, new ArrayClass([$subpredicate]));
    }

    /**
     * Returns a new predicate formed by OR-ing the predicates in a given array.
     * An OR predicate with no subpredicates evaluates to FALSE.
     * @param ArrayClass<covariant Predicate> $subpredicates An array of Predicate objects.
     * @return CompoundPredicate A new predicate formed by OR-ing the predicates specified by subpredicates.
     */
    public static function orPredicateWithSubpredicates(ArrayClass $subpredicates): CompoundPredicate
    {
        return new CompoundPredicate(CompoundPredicateLogicalType::or, $subpredicates);
    }

    #[Override]
    public function withSubstitutionVariables(Dictionary $variables): Predicate
    {
        return new CompoundPredicate($this->compoundPredicateType, $this->subpredicates->map(fn(Predicate $predicate): Predicate => $predicate->withSubstitutionVariables($variables)));
    }

    #[Override]
    public function evaluate(mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        return $this->predicateOperator->evaluatePredicates($this->subpredicates, $object, $substitutionVariables);
    }

    #[Override]
    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        $recursivelyAcceptVisitor = function () use ($visitor, $flags): void {
            /** @var Predicate $subpredicate */
            foreach ($this->subpredicates as $subpredicate) {
                $subpredicate->accept($visitor, $flags);
            }
        };
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicate($this);
            $recursivelyAcceptVisitor();
        } else {
            $recursivelyAcceptVisitor();
            $visitor->visitPredicate($this);
        }
    }
}
