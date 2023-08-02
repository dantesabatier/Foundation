<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 03/05/20
 * Time: 01:18
 */

namespace Sabatier\Foundation\Predicates;

use Exception;
use InvalidArgumentException;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Scanner;
use Throwable;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;
use function Sabatier\Foundation\typeof;

/** @internal */
class PredicateScanner extends Scanner
{
    public function __construct(string $format, private readonly ArrayClass $arguments)
    {
        parent::__construct($format);
        $this->charactersToBeSkipped = " \n";
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

    public function predicate(): ?Predicate
    {
        try {
            return $this->parsePredicate();
        } catch (Throwable $throwable) {
            $message = sprintf("Unable to parse predicate \"%s\" %s:%s", $this->string, typeof($throwable), human_readable_value($throwable));
            if (!$this->isAtEnd) {
                $message .= sprintf(" - Format string contains extra characters \"%s***%s***\"", substring_to_index($this->string, $this->scanLocation), substring_from_index($this->string, $this->scanLocation));
            }
            throw new InvalidArgumentException($message, (int)$throwable->getCode(), $throwable);
        }
    }

    /**
     * @throws Exception
     */
    private function parsePredicate(): ?Predicate
    {
        return $this->parseAnd();
    }

    /**
     * @throws Exception
     */
    private function parseAnd(): ?Predicate
    {
        $left = $this->parseOr();
        while ($this->scanKeyword("AND") || $this->scanKeyword("&&")) {
            $right = $this->parseOr();
            if ($right instanceof CompoundPredicate && ($right->compoundPredicateType === CompoundPredicateLogicalType::and)) {
                if ($left instanceof CompoundPredicate && ($left->compoundPredicateType === CompoundPredicateLogicalType::and)) {
                    $left->subpredicates->appendContentsOf($right->subpredicates);
                } else {
                    /** @psalm-suppress PossiblyNullArgument */
                    $right->subpredicates->append($left);
                    $left = $right;
                }
            } elseif ($left instanceof CompoundPredicate && ($left->compoundPredicateType === CompoundPredicateLogicalType::and)) {
                /** @psalm-suppress PossiblyNullArgument */
                $left->subpredicates->append($right);
            } else {
                /** @psalm-suppress InvalidArgument */
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
            if (!$this->scanString(")")) {
                throw new InvalidArgumentException("Invalid argument: missing closing \")\" at index $this->scanLocation");
            }
            return $predicate;
        }
        if ($this->scanKeyword("NOT") || $this->scanKeyword("!")) {
            /** @psalm-suppress PossiblyNullArgument */
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
        $left = $this->parseNot();
        while ($this->scanKeyword("OR") || $this->scanKeyword("||")) {
            $right = $this->parseNot();
            if ($right instanceof CompoundPredicate && ($right->compoundPredicateType === CompoundPredicateLogicalType::or)) {
                if ($left instanceof CompoundPredicate && ($left->compoundPredicateType === CompoundPredicateLogicalType::or)) {
                    $left->subpredicates->appendContentsOf($right->subpredicates);
                } else {
                    /** @psalm-suppress PossiblyNullArgument */
                    $right->subpredicates->append($left);
                    $left = $right;
                }
            } elseif ($left instanceof CompoundPredicate && ($left->compoundPredicateType === CompoundPredicateLogicalType::or)) {
                /** @psalm-suppress PossiblyNullArgument */
                $left->subpredicates->append($right);
            } else {
                /** @psalm-suppress InvalidArgument */
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
            $modifier = ComparisonPredicateModifier::all;
            $negate = true;
        } elseif ($this->scanKeyword("NONE")) {
            $modifier = ComparisonPredicateModifier::any;
            $negate = true;
        }
        $left = $this->parseExpression();
        if ($this->scanString("<=") || $this->scanString("=<")) {
            $operator = PredicateOperatorType::lessThanOrEqualTo;
        } elseif ($this->scanString(">=") || $this->scanString("=>")) {
            $operator = PredicateOperatorType::greaterThanOrEqualTo;
        } elseif ($this->scanString("<")) {
            $operator = PredicateOperatorType::lessThan;
        } elseif ($this->scanString(">")) {
            $operator = PredicateOperatorType::greaterThan;
        } elseif ($this->scanString("!=") || $this->scanString("<>")) {
            $operator = PredicateOperatorType::notEqualTo;
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
            throw new InvalidArgumentException("Invalid argument: unknown predicate operator type at index $this->scanLocation");
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
            throw new InvalidArgumentException(
                "Invalid argument: invalid option \"[d]\" at index $this->scanLocation",
                E_USER_WARNING
            );
        } elseif ($this->scanString("[n]")) {
            throw new InvalidArgumentException(
                "Invalid argument: invalid option \"[n]\" at index $this->scanLocation",
                E_USER_WARNING
            );
        }
        $right = $this->parseExpression();
        /** @psalm-suppress PossiblyNullArgument */
        $predicate = new ComparisonPredicate($left, $right, $operator, $modifier, $options);
        if ($negate) {
            $predicate = CompoundPredicate::notPredicateWithSubpredicate($predicate);
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
            /** @psalm-suppress InvalidArgument */
            return Expression::expressionForFunction("chs:", new ArrayClass([$this->parseExpression()]));
        }
        if ($this->scanString("(")) {
            $expression = $this->parseExpression();
            if (!$this->scanString(")")) {
                throw new InvalidArgumentException("Invalid argument: missing closing \")\" at index $this->scanLocation");
            }
            return $expression;
        }
        if ($this->scanString("{")) {
            /** @var ArrayClass<Expression> $subexpressions */
            $subexpressions = new ArrayClass();
            if ($this->scanString("}")) {
                return Expression::expressionForConstantValue($subexpressions);
            }
            /** @psalm-suppress PossiblyNullArgument */
            $subexpressions[] = $this->parseExpression(); // @phpstan-ignore-line
            while ($this->scanString(",")) {
                /** @psalm-suppress PossiblyNullArgument */
                $subexpressions[] = $this->parseExpression(); // @phpstan-ignore-line
            }
            if (!$this->scanString("}")) {
                throw new InvalidArgumentException("Invalid argument: missing closing \"}\" at index $this->scanLocation");
            }
            return Expression::expressionForAggregate($subexpressions); // @phpstan-ignore-line
        }
        if ($this->scanKeyword("TRUE") || $this->scanKeyword("YES")) {
            return Expression::expressionForConstantValue(true);
        } elseif ($this->scanKeyword("FALSE") || $this->scanKeyword("NO")) {
            return Expression::expressionForConstantValue(false);
        } elseif ($this->scanKeyword("NULL") || $this->scanKeyword("NIL")) {
            return Expression::expressionForConstantValue(null);
        } elseif ($this->scanKeyword("SELF")) {
            return Expression::expressionForEvaluatedObject();
        }
        if ($this->scanString("\$")) {
            if (!($keyPath = $this->parseSimpleExpression()?->keyPath())) {
                throw new InvalidArgumentException("Invalid argument: expecting key path");
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
                        return Expression::expressionForKeyPath($this->arguments->popFirst());
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
                        /** @psalm-suppress RedundantCondition */
                        if (!$this->isAtEnd) {
                            $c = $this->string[$this->scanLocation];
                            if ($c === "i" || $c === "u" || $c === "x" || $c === "X") {
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
            if (!$this->scanString("\"")) {
                throw new InvalidArgumentException("Invalid argument: missing closing \"\"\" at index $this->scanLocation");
            }
            $this->charactersToBeSkipped = $characters;
            return Expression::expressionForConstantValue($value);
        }
        if ($this->scanString("'")) {
            $value = "";
            $characters = $this->charactersToBeSkipped;
            $this->charactersToBeSkipped = "";
            $this->scanUpString("'", $value);
            if (!$this->scanString("'")) {
                throw new InvalidArgumentException("Invalid argument: missing closing \"'\" at index $this->scanLocation");
            }
            $this->charactersToBeSkipped = $characters;
            return Expression::expressionForConstantValue($value);
        }
        if ($this->scanString("@")) {
            if (!($keyPath = $this->parseSimpleExpression()?->keyPath())) {
                throw new InvalidArgumentException("Invalid argument: expecting expression at index $this->scanLocation");
            }
            return Expression::expressionForKeyPath("@$keyPath");
        }
        if ($this->scanString("SUBQUERY")) {
            if (!$this->scanString("(")) {
                throw new InvalidArgumentException("Invalid argument: expecting \"(\" at index $this->scanLocation");
            }
            $expression = $this->parseExpression() ?? throw new InvalidArgumentException("Invalid argument: expecting expression at index $this->scanLocation");
            if (!$this->scanString(",")) {
                throw new InvalidArgumentException("Invalid argument: expecting \",\" at index $this->scanLocation");
            }
            /** @phpstan-ignore-next-line */
            $variable = $this->parseExpression() ?? throw new InvalidArgumentException("Invalid argument: expecting expression at index $this->scanLocation");
            /** @phpstan-ignore-next-line */
            if (!$this->scanString(",")) {
                throw new InvalidArgumentException("Invalid argument: expecting \",\" at index $this->scanLocation");
            }
            $predicate = $this->parsePredicate() ?? throw new InvalidArgumentException("Invalid argument: expecting predicate at index $this->scanLocation");
            if (!$this->scanString(")")) {
                throw new InvalidArgumentException("Invalid argument: expecting \")\" at index $this->scanLocation");
            }
            return Expression::expressionForSubquery($expression, $variable, $predicate);
        }
        if ($this->scanString("TERNARY")) {
            if (!$this->scanString("(")) {
                throw new InvalidArgumentException("Invalid argument: expecting \"(\" at index $this->scanLocation");
            }
            $predicate = $this->parsePredicate() ?? throw new InvalidArgumentException("Invalid argument: expecting predicate at index $this->scanLocation");
            if (!$this->scanString(",")) {
                throw new InvalidArgumentException("Invalid argument: expecting \",\" at index $this->scanLocation");
            }
            $trueExpression = $this->parseExpression() ?? throw new InvalidArgumentException("Invalid argument: expecting expression at index $this->scanLocation");
            /** @phpstan-ignore-next-line */
            if (!$this->scanString(",")) {
                throw new InvalidArgumentException("Invalid argument: expecting \",\" at index $this->scanLocation");
            }
            /** @phpstan-ignore-next-line */
            $falseExpression = $this->parseExpression() ?? throw new InvalidArgumentException("Invalid argument: expecting expression at index $this->scanLocation");
            if (!$this->scanString(")")) {
                throw new InvalidArgumentException("Invalid argument: expecting \")\" at index $this->scanLocation");
            }
            return Expression::expressionForConditional($predicate, $trueExpression, $falseExpression);
        }
        if ($this->scanString("FUNCTION")) {
            if (!$this->scanString("(")) {
                throw new InvalidArgumentException("Invalid argument: expecting \"(\" at index $this->scanLocation");
            }
            /** @var ArrayClass<Expression> $arguments */
            $arguments = new ArrayClass();
            $argument = $this->parseExpression() ?? throw new InvalidArgumentException("Invalid argument: expecting expression at index $this->scanLocation");
            $arguments->append($argument);
            while ($this->scanString(",")) {
                /** @phpstan-ignore-next-line */
                $argument = $this->parseExpression() ?? throw new InvalidArgumentException("Invalid argument: expecting expression at index $this->scanLocation");
                $arguments->append($argument);
            }
            if (!$this->scanString(")")) {
                throw new InvalidArgumentException("Invalid argument: missing closing \")\" at index $this->scanLocation");
            }
            $operand = $arguments[0];
            $expression = $arguments[1];
            if ($expression->expressionType !== ExpressionType::constantValue && $expression->expressionType !== ExpressionType::keyPath) {
                throw new InvalidArgumentException(sprintf("Invalid argument: expecting constant expression, %s expression given at index %s", $expression->expressionType->name, $this->scanLocation));
            }
            return Expression::expressionForSelector($operand, $expression->constantValue(), new ArrayClass($arguments->dropFirst(2)));
        }
        $this->scanString("#");
        $value = "";
        $identifier = "_\$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        if (!$this->scanCharacters($identifier, $value)) {
            throw new InvalidArgumentException("Invalid argument: parsing error at index $this->scanLocation");
        }
        if ($value === null) {
            throw new InvalidArgumentException("Invalid argument: expecting value at index $this->scanLocation");
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
                /** @var Expression $right */
                $right = $this->parseSimpleExpression();
                $expressionType = $right->expressionType;
                if ($expressionType === ExpressionType::keyPath) {
                    /** @psalm-suppress PossiblyNullArgument */
                    $left = new KeyPathExpression($right, $left);
                } elseif ($expressionType === ExpressionType::variable || $expressionType === ExpressionType::constantValue) {
                    /** @psalm-suppress PossiblyNullArgument */
                    $left = Expression::expressionForSelector($left, "valueForKey", new ArrayClass([$right]));
                } else {
                    throw new InternalInconsistencyException(sprintf("%s %s() unhandled expression type \"%s\"", $this->debugDescription(), __FUNCTION__, $expressionType->name));
                }
            } elseif ($this->scanString("[")) {
                if ($this->scanKeyword("FIRST")) {
                    /** @psalm-suppress InvalidArgument */
                    $left = Expression::expressionForFunction("first:", new ArrayClass([$left]));
                } elseif ($this->scanKeyword("LAST")) {
                    /** @psalm-suppress InvalidArgument */
                    $left = Expression::expressionForFunction("last:", new ArrayClass([$left]));
                } elseif ($this->scanKeyword("SIZE")) {
                    /** @psalm-suppress InvalidArgument */
                    $left = Expression::expressionForFunction("size:", new ArrayClass([$left]));
                } else {
                    /** @psalm-suppress InvalidArgument */
                    $left = Expression::expressionForFunction("index:", new ArrayClass([$left, $this->parseExpression()]));
                }
                if (!$this->scanString("]", $string)) {
                    throw new InvalidArgumentException("Invalid argument: missing closing \"]\" at index $this->scanLocation");
                }
            } elseif ($left instanceof KeyPathExpression && $this->scanString(":")) {
                if (!($keyPath = $left->keyPath())) {
                    throw new InvalidArgumentException("Invalid argument: expecting key path at index $this->scanLocation");
                }
                $function = "$keyPath:";
                if (!$this->scanString("(")) {
                    $string = "";
                    $this->scanCharacters("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", $string);
                    if (!$this->scanString(":(")) {
                        throw new InvalidArgumentException("Invalid argument: expecting expression at index $this->scanLocation");
                    }
                    $function .= "$string:";
                }
                /** @var ArrayClass<Expression> $subexpressions */
                $subexpressions = new ArrayClass();
                if (!$this->scanString(")")) {
                    /** @psalm-suppress PossiblyNullArgument */
                    $subexpressions[] = $this->parseExpression(); // @phpstan-ignore-line
                    while ($this->scanString(",")) {
                        /** @psalm-suppress PossiblyNullArgument */
                        $subexpressions[] = $this->parseExpression(); // @phpstan-ignore-line
                    }
                    if (!$this->scanString(")")) {
                        throw new InvalidArgumentException("Invalid argument: missing closing \")\" at index $this->scanLocation");
                    }
                }
                $left = Expression::expressionForFunction($function, $subexpressions);
            } elseif ($this->scanString("UNION")) {
                /** @psalm-suppress PossiblyNullArgument */
                $left = Expression::expressionForUnionSet($left, $this->parseExpression());
            } elseif ($this->scanString("INTERSECT")) {
                /** @psalm-suppress PossiblyNullArgument */
                $left = Expression::expressionForIntersectSet($left, $this->parseExpression());
            } elseif ($this->scanString("MINUS")) {
                /** @psalm-suppress PossiblyNullArgument */
                $left = Expression::expressionForMinusSet($left, $this->parseExpression());
            } else {
                return $left;
            }
        }
    }

    /**
     * @throws Exception
     */
    private function parseModulusExpression(): ?Expression
    {
        $left = $this->parseFunctionalExpression();
        while (true) {
            if ($this->scanString("%")) {
                $right = $this->parseFunctionalExpression();
                /** @psalm-suppress InvalidArgument */
                $left = Expression::expressionForFunction("modulus:by:", new ArrayClass([$left, $right]));
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
        $left = $this->parseModulusExpression();
        while (true) {
            if ($this->scanString("**")) {
                $right = $this->parseModulusExpression();
                /** @psalm-suppress InvalidArgument */
                $left = Expression::expressionForFunction("raise:toPower:", new ArrayClass([$left, $right]));
            } else {
                return $left;
            }
        }
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
                /** @psalm-suppress InvalidArgument */
                $left = Expression::expressionForFunction("multiply:by:", new ArrayClass([$left, $right]));
            } elseif ($this->scanString("/")) {
                $right = $this->parsePowerExpression();
                /** @psalm-suppress InvalidArgument */
                $left = Expression::expressionForFunction("divide:by:", new ArrayClass([$left, $right]));
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
                /** @psalm-suppress InvalidArgument */
                $left = Expression::expressionForFunction("add:to:", new ArrayClass([$left, $right]));
            } elseif ($this->scanString("-")) {
                $right = $this->parseMultiplicationExpression();
                /** @psalm-suppress InvalidArgument */
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
                    throw new InvalidArgumentException("Invalid argument: expecting expression after :=");
                }
                assert($left instanceof VariableExpression, sprintf("Invalid argument: expecting \"%s\", \"%s\" given", VariableExpression::class, typeof($left)));
                $left = new VariableAssignmentExpression($left, $right);
            } else {
                return $left;
            }
        }
    }
}
