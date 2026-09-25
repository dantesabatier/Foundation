<?php

/** @noinspection SpellCheckingInspection */

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 03/05/20
 * Time: 01:18
 */

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Scanner;
use Throwable;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;
use function Sabatier\Foundation\typeof;

/** @internal */
final class PredicateScanner extends Scanner
{
    private readonly ArrayClass $arguments;
    public ?Predicate $predicate {
        get {
            try {
                return $this->parsePredicate();
            } catch (Throwable $throwable) {
                $message = sprintf("Unable to parse predicate \"%s\" %s:%s", $this->string, typeof($throwable), human_readable_value($throwable));
                if (!$this->isAtEnd) {
                    $message .= sprintf(" - Format string contains extra characters \"%s***%s***\"", substring_to_index($this->string, $this->scanLocation), substring_from_index($this->string, $this->scanLocation));
                }
                throw new InternalInconsistencyException($message, (int)$throwable->getCode(), previous: $throwable);
            }
        }
    }
    #[Override]
    public string $charactersToBeSkipped = " \r\n";

    public function __construct(string $format, ArrayClass $arguments)
    {
        parent::__construct($format);
        $this->arguments = clone $arguments;
    }

    private function scanKeyword(string $keyword): bool
    {
        $scanLocation = $this->scanLocation;
        if (!$this->scanString($keyword)) {
            return false;
        }
        if ($this->isAtEnd) {
            return true;
        }
        $char = $this->string[$this->scanLocation];
        if (!ctype_alnum($char)) {
            return true;
        }
        $this->scanLocation = $scanLocation;
        return false;
    }

    private function scanSingleQuotedString(): string
    {
        $value = "";
        while (!$this->isAtEnd) {
            $character = mb_substr($this->string, $this->scanLocation, 1);
            $this->scanLocation++;
            if ($character === "'") {
                return $value;
            }
            if ($character !== "\\" || $this->isAtEnd) {
                $value .= $character;
                continue;
            }
            $escaped = mb_substr($this->string, $this->scanLocation, 1);
            if ($escaped === "\\" || $escaped === "'") {
                $this->scanLocation++;
                $value .= $escaped;
            } else {
                $value .= $character;
            }
        }
        fatal_error("Invalid argument: missing closing \"'\" at index $this->scanLocation");
    }

    /**
     * @throws Exception
     */
    private function parsePredicate(): ?Predicate
    {
        return $this->parseOr();
    }

    /**
     * @throws Exception
     */
    private function parseAnd(): ?Predicate
    {
        $left = $this->parseNot();
        while ($this->scanKeyword("AND") || $this->scanKeyword("&&")) {
            $right = $this->parseNot();
            if ($right instanceof CompoundPredicate && ($right->compoundPredicateType === CompoundPredicateLogicalType::and)) {
                if ($left instanceof CompoundPredicate && ($left->compoundPredicateType === CompoundPredicateLogicalType::and)) {
                    $left->subpredicates->appendContentsOf($right->subpredicates);
                } else {
                    assert($left instanceof Predicate);
                    $right->subpredicates->append($left);
                    $left = $right;
                }
            } elseif ($left instanceof CompoundPredicate && ($left->compoundPredicateType === CompoundPredicateLogicalType::and)) {
                assert($right instanceof Predicate);
                $left->subpredicates->append($right);
            } else {
                assert($left instanceof Predicate && $right instanceof Predicate);
                $left = CompoundPredicate::andPredicateWithSubpredicates(new ArrayClass([$left, $right]));
            }
        }
        return $left;
    }

    /**
     * @throws Exception
     */
    private function parseNot(): ?Predicate
    {
        if ($this->scanString("(")) {
            $predicate = $this->parsePredicate();
            $this->scanString(")") ?: fatal_error("Invalid argument: missing closing \")\" at index $this->scanLocation");
            return $predicate;
        }
        if ($this->scanKeyword("NOT") || $this->scanKeyword("!")) {
            return CompoundPredicate::notPredicateWithSubpredicate($this->parseNot());
        }
        if ($this->scanKeyword("TRUEPREDICATE")) {
            return Predicate::value(true);
        }
        if ($this->scanKeyword("FALSEPREDICATE")) {
            return Predicate::value(false);
        }
        return $this->parseComparison();
    }

    /**
     * @throws Exception
     */
    private function parseOr(): ?Predicate
    {
        $left = $this->parseAnd();
        while ($this->scanKeyword("OR") || $this->scanKeyword("||")) {
            $right = $this->parseAnd();
            if ($right instanceof CompoundPredicate && ($right->compoundPredicateType === CompoundPredicateLogicalType::or)) {
                if ($left instanceof CompoundPredicate && ($left->compoundPredicateType === CompoundPredicateLogicalType::or)) {
                    $left->subpredicates->appendContentsOf($right->subpredicates);
                } else {
                    assert($left instanceof Predicate);
                    $right->subpredicates->append($left);
                    $left = $right;
                }
            } elseif ($left instanceof CompoundPredicate && ($left->compoundPredicateType === CompoundPredicateLogicalType::or)) {
                assert($right instanceof Predicate);
                $left->subpredicates->append($right);
            } else {
                assert($left instanceof Predicate && $right instanceof Predicate);
                $left = CompoundPredicate::orPredicateWithSubpredicates(new ArrayClass([$left, $right]));
            }
        }
        return $left;
    }

    /**
     * @throws Exception
     */
    private function parseComparison(): Predicate
    {
        $negate = false;
        $options = ComparisonPredicateOptions::none;
        $modifier = ComparisonPredicateModifier::direct;
        if ($this->scanKeyword("ANY")) {
            $modifier = ComparisonPredicateModifier::any;
        } elseif ($this->scanKeyword("ALL")) {
            $modifier = ComparisonPredicateModifier::all;
        } elseif ($this->scanKeyword("SOME")) {
            // SOME is a synonym for ANY, not the negation of ALL: mapping it to "NOT ALL" inverted the answer wherever the two differ — over {5,5,5} "SOME nums == 5" replied false.
            $modifier = ComparisonPredicateModifier::any;
        } elseif ($this->scanKeyword("NONE")) {
            $modifier = ComparisonPredicateModifier::any;
            $negate = true;
        }
        $left = $this->parseExpression();
        // Longest match first: "<" would otherwise consume the opening character of "<>" and leave "> 3" behind, so the two-character operators have to be tried before the one-character ones.
        if ($this->scanString("<=") || $this->scanString("=<")) {
            $operator = PredicateOperatorType::lessThanOrEqualTo;
        } elseif ($this->scanString(">=") || $this->scanString("=>")) {
            $operator = PredicateOperatorType::greaterThanOrEqualTo;
        } elseif ($this->scanString("!=") || $this->scanString("<>")) {
            $operator = PredicateOperatorType::notEqualTo;
        } elseif ($this->scanString("<")) {
            $operator = PredicateOperatorType::lessThan;
        } elseif ($this->scanString(">")) {
            $operator = PredicateOperatorType::greaterThan;
        } elseif ($this->scanString("==") || $this->scanString("=")) {
            $operator = PredicateOperatorType::equalTo;
        } elseif ($this->scanKeyword("LIKE")) {
            $operator = PredicateOperatorType::like;
        } elseif ($this->scanKeyword("MATCHES")) {
            $operator = PredicateOperatorType::matches;
        } elseif ($this->scanKeyword("BEGINSWITH")) {
            $operator = PredicateOperatorType::beginsWith;
        } elseif ($this->scanKeyword("ENDSWITH")) {
            $operator = PredicateOperatorType::endsWith;
        } elseif ($this->scanKeyword("CONTAINS")) {
            $operator = PredicateOperatorType::contains;
        } elseif ($this->scanKeyword("IN")) {
            $operator = PredicateOperatorType::in;
        } elseif ($this->scanKeyword("BETWEEN")) {
            $operator = PredicateOperatorType::between;
        } else {
            fatal_error("Invalid argument: unknown predicate operator type at index $this->scanLocation");
        }
        if ($this->scanString("[cdnl]")) {
            $options = ComparisonPredicateOptions::caseInsensitive | ComparisonPredicateOptions::diacriticInsensitive | ComparisonPredicateOptions::normalized | ComparisonPredicateOptions::localeSensitive;
        } elseif ($this->scanString("[cdn]")) {
            $options = ComparisonPredicateOptions::caseInsensitive | ComparisonPredicateOptions::diacriticInsensitive | ComparisonPredicateOptions::normalized;
        } elseif ($this->scanString("[cdl]")) {
            $options = ComparisonPredicateOptions::caseInsensitive | ComparisonPredicateOptions::diacriticInsensitive | ComparisonPredicateOptions::localeSensitive;
        } elseif ($this->scanString("[cd]")) {
            $options = ComparisonPredicateOptions::caseInsensitive | ComparisonPredicateOptions::diacriticInsensitive;
        } elseif ($this->scanString("[cl]")) {
            $options = ComparisonPredicateOptions::caseInsensitive | ComparisonPredicateOptions::localeSensitive;
        } elseif ($this->scanString("[c]")) {
            $options = ComparisonPredicateOptions::caseInsensitive;
        } elseif ($this->scanString("[l]")) {
            $options = ComparisonPredicateOptions::localeSensitive;
        } elseif ($this->scanString("[d]")) {
            fatal_error("Invalid argument: invalid option \"[d]\" at index $this->scanLocation");
        } elseif ($this->scanString("[n]")) {
            fatal_error("Invalid argument: invalid option \"[n]\" at index $this->scanLocation");
        }
        $right = $this->parseExpression();
        assert($left instanceof Expression && $right instanceof Expression);
        $predicate = new ComparisonPredicate($left, $right, $operator, $modifier, $options);
        if ($negate) {
            return CompoundPredicate::notPredicateWithSubpredicate($predicate);
        }
        return $predicate;
    }

    /**
     * @throws Exception
     */
    private function parseExpression(): ?Expression
    {
        return $this->parseBinaryExpression();
    }

    /**
     * @throws Exception
     */
    private function parseSimpleExpression(): ?Expression
    {
        $number = 0;
        if ($this->scanFloat($number)) {
            return Expression::expressionForConstantValue($number);
        }
        if ($this->scanString("-")) {
            $expression = $this->parseFunctionalExpression();
            assert($expression instanceof Expression);
            return Expression::expressionForFunction("chs:", new ArrayClass([$expression]));
        }
        if ($this->scanString("(")) {
            $expression = $this->parseExpression();
            $this->scanString(")") ?: fatal_error("Invalid argument: missing closing \")\" at index $this->scanLocation");
            return $expression;
        }
        if ($this->scanString("{")) {
            /** @var ArrayClass<Expression> $subexpressions */
            $subexpressions = new ArrayClass();
            if ($this->scanString("}")) {
                return Expression::expressionForConstantValue($subexpressions);
            }
            $expression = $this->parseExpression();
            assert($expression instanceof Expression);
            $subexpressions->append($expression);
            while ($this->scanString(",")) {
                $expression = $this->parseExpression();
                assert($expression instanceof Expression);
                $subexpressions->append($expression);
            }
            $this->scanString("}") ?: fatal_error("Invalid argument: missing closing \"}\" at index $this->scanLocation");
            return Expression::expressionForAggregate($subexpressions);
        }
        if ($this->scanKeyword("TRUE") || $this->scanKeyword("YES")) {
            return Expression::expressionForConstantValue(true);
        }
        if ($this->scanKeyword("FALSE") || $this->scanKeyword("NO")) {
            return Expression::expressionForConstantValue(false);
        }
        if ($this->scanKeyword("NULL") || $this->scanKeyword("NIL")) {
            return Expression::expressionForConstantValue(null);
        }
        if ($this->scanKeyword("SELF")) {
            return Expression::expressionForEvaluatedObject();
        }
        if ($this->scanString("\$")) {
            if (!($keyPath = $this->parseSimpleExpression()?->keyPath)) {
                fatal_error("Invalid argument: expecting key path");
            }
            return Expression::expressionForVariable("\$$keyPath");
        }
        $scanLocation = $this->scanLocation;
        if ($this->scanString("%")) {
            if (!$this->isAtEnd) {
                $c = $this->string[$this->scanLocation];
                switch ($c) {
                    case "%":
                        $scanLocation = $this->scanLocation;
                        break;
                    case "K":
                        $this->scanLocation += 1;
                        return Expression::expressionForKeyPath((string)$this->arguments->popFirst());
                    case "@":
                    case "s":
                    case "c":
                    case "C":
                    case "d":
                    case "D":
                    case "i":
                    case "o":
                    case "O":
                    case "u":
                    case "U":
                    case "x":
                    case "X":
                    case "e":
                    case "E":
                    case "f":
                    case "g":
                    case "G":
                        $this->scanLocation += 1;
                        return Expression::expressionForConstantValue($this->arguments->popFirst());
                    case "h":
                        $this->scanString("h");
                        $c = $this->string[$this->scanLocation];
                        if ($c === "i" || $c === "u") {
                            $this->scanLocation += 1;
                            return Expression::expressionForConstantValue($this->arguments->popFirst());
                        }
                        break;
                    case "q":
                        $this->scanString("q");
                        if (!$this->isAtEnd) {
                            $c = $this->string[$this->scanLocation];
                            if (in_array($c, ["i", "u", "x", "X"], true)) {
                                $this->scanLocation += 1;
                                return Expression::expressionForConstantValue($this->arguments->popFirst());
                            }
                        }
                        break;
                    default:
                        break;
                }
            }
            $this->scanLocation = $scanLocation;
        }
        if ($this->scanString("\"")) {
            $value = "";
            $characters = $this->charactersToBeSkipped;
            $this->charactersToBeSkipped = "";
            $this->scanUpString("\"", $value);
            $this->scanString("\"") ?: fatal_error("Invalid argument: missing closing \"\"\" at index $this->scanLocation");
            $this->charactersToBeSkipped = $characters;
            return Expression::expressionForConstantValue($value);
        }
        if ($this->scanString("'")) {
            return Expression::expressionForConstantValue($this->scanSingleQuotedString());
        }
        if ($this->scanString("@")) {
            if (!($keyPath = $this->parseSimpleExpression()?->keyPath)) {
                fatal_error("Invalid argument: expecting expression at index $this->scanLocation");
            }
            return Expression::expressionForKeyPath("@$keyPath");
        }
        if ($this->scanString("SUBQUERY")) {
            $this->scanString("(") ?: fatal_error("Invalid argument: expecting \"(\" at index $this->scanLocation");
            $expression = $this->parseExpression() ?? fatal_error("Invalid argument: expecting expression at index $this->scanLocation");
            $this->scanString(",") ?: fatal_error("Invalid argument: expecting \",\" at index $this->scanLocation");
            $variable = $this->parseExpression() ?? fatal_error("Invalid argument: expecting expression at index $this->scanLocation");
            $this->scanString(",") ?: fatal_error("Invalid argument: expecting \",\" at index $this->scanLocation");
            $predicate = $this->parsePredicate() ?? fatal_error("Invalid argument: expecting predicate at index $this->scanLocation");
            $this->scanString(")") ?: fatal_error("Invalid argument: expecting \")\" at index $this->scanLocation");
            return Expression::expressionForSubquery($expression, $variable, $predicate);
        }
        if ($this->scanString("TERNARY")) {
            $this->scanString("(") ?: fatal_error("Invalid argument: expecting \"(\" at index $this->scanLocation");
            $predicate = $this->parsePredicate() ?? fatal_error("Invalid argument: expecting predicate at index $this->scanLocation");
            $this->scanString(",") ?: fatal_error("Invalid argument: expecting \",\" at index $this->scanLocation");
            $trueExpression = $this->parseExpression() ?? fatal_error("Invalid argument: expecting expression at index $this->scanLocation");
            $this->scanString(",") ?: fatal_error("Invalid argument: expecting \",\" at index $this->scanLocation");
            $falseExpression = $this->parseExpression() ?? fatal_error("Invalid argument: expecting expression at index $this->scanLocation");
            $this->scanString(")") ?: fatal_error("Invalid argument: expecting \")\" at index $this->scanLocation");
            return Expression::expressionForConditional($predicate, $trueExpression, $falseExpression);
        }
        if ($this->scanString("FUNCTION")) {
            $this->scanString("(") ?: fatal_error("Invalid argument: expecting \"(\" at index $this->scanLocation");
            /** @var ArrayClass<Expression> $arguments */
            $arguments = new ArrayClass();
            $argument = $this->parseExpression() ?? fatal_error("Invalid argument: expecting expression at index $this->scanLocation");
            $arguments->append($argument);
            while ($this->scanString(",")) {
                $argument = $this->parseExpression() ?? fatal_error("Invalid argument: expecting expression at index $this->scanLocation");
                $arguments->append($argument);
            }
            $this->scanString(")") ?: fatal_error("Invalid argument: missing closing \")\" at index $this->scanLocation");
            $operand = $arguments[0];
            $expression = $arguments[1];
            if ($expression->expressionType !== ExpressionType::constantValue && $expression->expressionType !== ExpressionType::keyPath) {
                fatal_error(sprintf("Invalid argument: expecting constant expression, %s expression given at index %s", $expression->expressionType->name, $this->scanLocation));
            }
            return Expression::expressionForSelector($operand, $expression->constantValue, new ArrayClass($arguments->dropFirst(2)));
        }
        $this->scanString("#");
        $value = "";
        $identifier = "_\$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $this->scanCharacters($identifier, $value) ?: fatal_error("Invalid argument: parsing error at index $this->scanLocation");
        if ($value === null) {
            fatal_error("Invalid argument: expecting value at index $this->scanLocation");
        }
        return Expression::expressionForKeyPath($value);
    }

    /**
     * @throws Exception
     */
    private function parseFunctionalExpression(): ?Expression
    {
        $left = $this->parseSimpleExpression();
        while (true) {
            if ($this->scanString(".")) {
                $right = $this->parseSimpleExpression();
                assert($right instanceof Expression);
                $expressionType = $right->expressionType;
                if ($expressionType === ExpressionType::keyPath) {
                    assert($left instanceof Expression);
                    $left = new KeyPathExpression($right, $left);
                } elseif ($expressionType === ExpressionType::variable || $expressionType === ExpressionType::constantValue) {
                    assert($left instanceof Expression);
                    $left = Expression::expressionForSelector($left, "valueForKey", new ArrayClass([$right]));
                } else {
                    fatal_error(sprintf("%s %s() unhandled expression type \"%s\"", $this->debugDescription, __FUNCTION__, $expressionType->name));
                }
            } elseif ($this->scanString("[")) {
                if ($this->scanKeyword("FIRST")) {
                    assert($left instanceof Expression);
                    $left = Expression::expressionForFunction("first:", new ArrayClass([$left]));
                } elseif ($this->scanKeyword("LAST")) {
                    assert($left instanceof Expression);
                    $left = Expression::expressionForFunction("last:", new ArrayClass([$left]));
                } elseif ($this->scanKeyword("SIZE")) {
                    assert($left instanceof Expression);
                    $left = Expression::expressionForFunction("size:", new ArrayClass([$left]));
                } else {
                    $expression = $this->parseExpression();
                    assert($expression instanceof Expression);
                    assert($left instanceof Expression);
                    $left = Expression::expressionForFunction("index:", new ArrayClass([$left, $expression]));
                }
                $this->scanString("]", $string) ?: fatal_error("Invalid argument: missing closing \"]\" at index $this->scanLocation");
            } elseif ($left instanceof KeyPathExpression && $this->scanString(":")) {
                if (!($keyPath = $left->keyPath)) {
                    fatal_error("Invalid argument: expecting key path at index $this->scanLocation");
                }
                $function = "$keyPath:";
                if (!$this->scanString("(")) {
                    $string = "";
                    $this->scanCharacters("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", $string);
                    $this->scanString(":(") ?: fatal_error("Invalid argument: missing closing \":(\" at index $this->scanLocation");
                    $function .= "$string:";
                }
                /** @var ArrayClass<Expression> $subexpressions */
                $subexpressions = new ArrayClass();
                if (!$this->scanString(")")) {
                    $expression = $this->parseExpression();
                    assert($expression instanceof Expression);
                    $subexpressions->append($expression);
                    while ($this->scanString(",")) {
                        $expression = $this->parseExpression();
                        assert($expression instanceof Expression);
                        $subexpressions->append($expression);
                    }
                    $this->scanString(")") ?: fatal_error("Invalid argument: missing closing \")\" at index $this->scanLocation");
                }
                $left = Expression::expressionForFunction($function, $subexpressions);
            } elseif ($this->scanString("UNION")) {
                $right = $this->parseExpression();
                assert($right instanceof Expression);
                $left = Expression::expressionForUnionSet($left, $right);
            } elseif ($this->scanString("INTERSECT")) {
                $right = $this->parseExpression();
                assert($right instanceof Expression);
                $left = Expression::expressionForIntersectSet($left, $right);
            } elseif ($this->scanString("MINUS")) {
                $right = $this->parseExpression();
                assert($right instanceof Expression);
                $left = Expression::expressionForMinusSet($left, $right);
            } else {
                return $left;
            }
        }
    }

    /**
     * @throws Exception
     */
    private function parsePowerExpression(): ?Expression
    {
        $left = $this->parseFunctionalExpression();
        if ($this->scanString("**")) {
            $right = $this->parsePowerExpression();
            assert($right instanceof Expression);
            assert($left instanceof Expression);
            return Expression::expressionForFunction("raise:toPower:", new ArrayClass([$left, $right]));
        }
        return $left;
    }

    /**
     * @throws Exception
     */
    private function parseMultiplicationExpression(): ?Expression
    {
        $left = $this->parsePowerExpression();
        while (true) {
            if ($this->scanString("*")) {
                $right = $this->parsePowerExpression();
                assert($right instanceof Expression);
                assert($left instanceof Expression);
                $left = Expression::expressionForFunction("multiply:by:", new ArrayClass([$left, $right]));
            } elseif ($this->scanString("/")) {
                $right = $this->parsePowerExpression();
                assert($right instanceof Expression);
                assert($left instanceof Expression);
                $left = Expression::expressionForFunction("divide:by:", new ArrayClass([$left, $right]));
            } elseif ($this->scanString("%")) {
                $right = $this->parsePowerExpression();
                assert($right instanceof Expression);
                assert($left instanceof Expression);
                $left = Expression::expressionForFunction("modulus:by:", new ArrayClass([$left, $right]));
            } else {
                return $left;
            }
        }
    }

    /**
     * @throws Exception
     */
    private function parseAdditionExpression(): ?Expression
    {
        $left = $this->parseMultiplicationExpression();
        while (true) {
            if ($this->scanString("+")) {
                $right = $this->parseMultiplicationExpression();
                assert($right instanceof Expression);
                assert($left instanceof Expression);
                $left = Expression::expressionForFunction("add:to:", new ArrayClass([$left, $right]));
            } elseif ($this->scanString("-")) {
                $right = $this->parseMultiplicationExpression();
                assert($right instanceof Expression);
                assert($left instanceof Expression);
                $left = Expression::expressionForFunction("from:subtract:", new ArrayClass([$left, $right]));
            } else {
                return $left;
            }
        }
    }

    /**
     * @throws Exception
     */
    private function parseBinaryExpression(): ?Expression
    {
        $left = $this->parseAdditionExpression();
        while (true) {
            if ($this->scanString(":=")) {
                if (!($right = $this->parseAdditionExpression())) {
                    fatal_error("Invalid argument: expecting expression after :=");
                }
                $left instanceof VariableExpression ?: $left
                        |> typeof(...)
                        |> (fn(string $x): string => sprintf("Invalid argument: expecting \"%s\", \"%s\" given", VariableExpression::class, $x))
                        |> fatal_error(...);
                $left = new VariableAssignmentExpression($left, $right);
            } else {
                return $left;
            }
        }
    }
}
