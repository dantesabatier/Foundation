<?php

namespace Sabatier\Foundation\Predicates;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use function Sabatier\Foundation\request_concrete_implementation;

/**
 * An expression for use in a comparison predicate.
 */
class Expression extends ObjectClass
{
    /** @var ArrayClass<Expression>|null The arguments for the expression. An expression's arguments are the array of expressions that will be passed as parameters during invocation of the selector on the operand of a function expression. Accessing this property raises an exception if it is not applicable to the expression. */
    protected(set) ?ArrayClass $arguments {
        get => $this->arguments ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var mixed The collection of expressions in an aggregate expression, or the collection element of a subquery expression. Accessing this property raises an exception if it is not applicable to the expression. */
    protected(set) mixed $collection {
        get => $this->collection ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var mixed The constant value of the expression. */
    protected(set) mixed $constantValue {
        get => $this->constantValue ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var string The function for the expression. Accessing this property raises an exception if it is not applicable to the expression. */
    protected(set) string $function {
        get => $this->function ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var string The key path for the expression. Accessing this property raises an exception if it is not applicable to the expression. */
    protected(set) string $keyPath {
        get => $this->keyPath ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var Expression|null The operand for the expression. Accessing this property raises an exception if it is not applicable to the expression. The operand for an expression is the object on which the expression's selector or block will be invoked. The object is the result of evaluating a key path or one of the defined functions. */
    protected(set) ?Expression $operand {
        get => $this->operand ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var Predicate The predicate of a subquery expression. Accessing this property raises an exception if it is not applicable to the expression. */
    protected(set) Predicate $predicate {
        get => $this->predicate ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var Expression The left expression of an aggregate expression. Accessing this property raises an exception if it is not applicable to the expression. */
    protected(set) Expression $left {
        get => $this->left ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var Expression The right expression of an aggregate expression. Accessing this property raises an exception if it is not applicable to the expression. */
    protected(set) Expression $right {
        get => $this->right ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var string The variable for the expression. Accessing this property raises an exception if it is not applicable to the expression. */
    protected(set) string $variable {
        get => $this->variable ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var Closure(mixed, ArrayClass<Expression>, Dictionary<mixed>|null): mixed Accessing this property raises an exception if it is not applicable to the expression. */
    protected(set) Closure $expressionBlock {
        get => $this->expressionBlock ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var Expression Accessing this property raises an exception if it is not applicable to the expression. */
    protected(set) Expression $true {
        get => $this->true ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @var Expression Accessing this property raises an exception if it is not applicable to the expression. */
    protected(set) Expression $false {
        get => $this->false ??= request_concrete_implementation($this, __PROPERTY__);
    }
    /** @internal */
    public string $predicateFormat {
        get => request_concrete_implementation($this, __PROPERTY__);
    }
    public string $description {
        get => $this->predicateFormat;
    }

    /**
     * Initializes the expression with the specified expression type.
     *
     * @param ExpressionType $expressionType The type of the new expression, as defined by {@see ExpressionType}.
     */
    protected function __construct(public readonly ExpressionType $expressionType = ExpressionType::undefined)
    {
    }

    /**
     * Initializes the expression with the specified expression format and array of arguments.
     * @param string $format The expression format.
     * @param ArrayClass $arguments An array of arguments to be used with the expressionFormat string.
     * @return Expression|null An initialized Expression object with the specified arguments.
     */
    public static function expressionWithFormat(string $format, ArrayClass $arguments = new ArrayClass()): ?Expression
    {
        if (($predicate = Predicate::format("$format = 1", $arguments)) && $predicate instanceof ComparisonPredicate) {
            return $predicate->leftExpression;
        }
        return null;
    }

    /**
     * Returns a new expression that represents a given constant value.
     * @param mixed $obj The constant value the new expression is to represent.
     * @return Expression A new expression that represents the constant value, $obj.
     */
    public static function expressionForConstantValue(mixed $obj): Expression
    {
        return new ConstantValueExpression($obj);
    }

    /**
     * Returns a new expression that represents the object being evaluated.
     * @return Expression A new expression that represents the object being evaluated.
     */
    public static function expressionForEvaluatedObject(): Expression
    {
        return new SelfExpression();
    }

    /**
     * Returns a new expression that invokes {@see KeyValueCoding::valueForKeyPath()} with a given key path.
     * @param string $keyPath The key path that the new expression should evaluate.
     * @return Expression A new expression that invokes {@see KeyValueCoding::valueForKeyPath()} with keyPath.
     */
    public static function expressionForKeyPath(string $keyPath): Expression
    {
        return new KeyPathExpression(new KeyPathSpecifierExpression($keyPath), Expression::expressionForEvaluatedObject());
    }

    /**
     * Returns a new expression that extracts a value from the variable bindings dictionary for a given key.
     * @param string $variable The key for the variable to extract from the variable bindings dictionary.
     * @return Expression A new expression that extracts from the variable bindings dictionary the value for the key string.
     */
    public static function expressionForVariable(string $variable): Expression
    {
        return new VariableExpression($variable);
    }

    /**
     * Returns a new expression that represents any key for a Spotlight query.
     * @return Expression A new expression that represents any key for a Spotlight query.
     */
    public static function expressionForAnyKey(): Expression
    {
        return new AnyKeyExpression();
    }

    /**
     * Returns a new aggregate expression for a given collection.
     * @param ArrayClass<Expression> $subexpressions A collection object that contains further expressions.
     * @return Expression A new expression that contains the expressions in $subexpressions.
     */
    public static function expressionForAggregate(ArrayClass $subexpressions): Expression
    {
        return new AggregateExpression($subexpressions);
    }

    /**
     * Returns a new Expression that represents the union of a given set and collection.
     * @param Expression $left An expression that evaluates to a Set object.
     * @param Expression $right An expression that evaluates to a collection object.
     * @return Expression A new Expression object that represents the union of left and right.
     */
    public static function expressionForUnionSet(Expression $left, Expression $right): Expression
    {
        return new SetExpression(ExpressionType::unionSet, $left, $right);
    }

    /**
     * Returns a new Expression that represents the intersection of a given set and collection.
     * @param Expression $left An expression that evaluates to a Set object.
     * @param Expression $right An expression that evaluates to a collection object.
     * @return Expression A new Expression object that represents the intersection of left and right.
     */
    public static function expressionForIntersectSet(Expression $left, Expression $right): Expression
    {
        return new SetExpression(ExpressionType::intersectSet, $left, $right);
    }

    /**
     * Returns a new Expression that represents the subtraction of a given collection from a given set.
     * @param Expression $left An expression that evaluates to a Set object.
     * @param Expression $right An expression that evaluates to a collection object.
     * @return Expression A new Expression object that represents the subtraction of $right from $left.
     */
    public static function expressionForMinusSet(Expression $left, Expression $right): Expression
    {
        return new SetExpression(ExpressionType::minusSet, $left, $right);
    }

    /**
     * Returns an expression that filters a collection by storing elements in the collection in a given variable and keeping the elements for which qualifier returns true.
     * @param Expression $expression A predicate expression that evaluates to a collection.
     * @param Expression $variable Used as a local variable, and will shadow any instances of variable in the Bindings dictionary.
     * The variable is removed or the old value replaced once evaluation completes.
     * @param Predicate $predicate The predicate used to determine whether the element belongs in the result collection.
     * @return Expression This method creates a sub-expression, the evaluation of which returns a subset of a collection of objects.
     * It allows you to create sophisticated queries across relationships, such as a search for multiple correlated values on the destination object of a relationship.
     */
    public static function expressionForSubquery(Expression $expression, Expression $variable, Predicate $predicate): Expression
    {
        return new SubqueryExpression($expression, $variable, $predicate);
    }

    /**
     * Creates an Expression object that will use the Block for evaluating objects.
     * @param Closure(mixed, ArrayClass<Expression>, Dictionary<mixed>|null): mixed $block The Block is applied to the object to be evaluated.
     * @param ArrayClass<Expression>|null $arguments An array containing Expression objects that will be used as parameters during the invocation of selector.
     * For a selector taking no parameters, the array should be empty.
     * For a selector taking one or more parameters, the array should contain one Expression object which will evaluate to an instance of the appropriate type for each parameter.
     * If there is a mismatch between the number of parameters expected and the number you provide during evaluation, an exception may be raised or missing parameters may simply be replaced by null (which occurs depends on how many parameters are provided, and whether you have over- or underflow).
     * See ({@see expressionForFunction()}) for a complete list of arguments.
     * @return Expression An expression that filters a collection using the specified Block.
     */
    public static function expressionForBlock(Closure $block, ?ArrayClass $arguments = null): Expression
    {
        return new BlockExpression($block, $arguments);
    }

    /**
     * Returns a new expression that will invoke one of the predefined functions.
     * @param string $name The name of the function to invoke.
     * @param ArrayClass<Expression> $parameters An array containing Expression objects that will be used as parameters during the invocation of selector.
     * For a selector taking no parameters, the array should be empty.
     * For a selector taking one or more parameters, the array should contain one Expression object which will evaluate to an instance of the appropriate type for each parameter.
     * If there is a mismatch between the number of parameters expected and the number you provide during evaluation, an exception may be raised or missing parameters may simply be replaced by null (which occurs depends on how many parameters are provided, and whether you have over or underflow).
     * @return Expression A new expression that invokes the function name using the parameters in parameters.
     */
    public static function expressionForFunction(string $name, ArrayClass $parameters): Expression
    {
        return FunctionExpression::functionWithName($name, $parameters);
    }

    /**
     * Returns an expression which will return the result of invoking on a given target a selector with a given name using given arguments.
     * @param Expression $target An Expression object which will evaluate an object on which the selector identified by name may be invoked.
     * @param string $selector The name of the method to be invoked.
     * @param ArrayClass<Expression>|null $arguments An array containing Expression objects which can be evaluated to provide parameters for the method specified by name.
     * @return Expression An expression which will return the result of invoking the selector named name on the result of evaluating the target expression with the parameters specified by evaluating the elements of parameters. This expression effectively allows your application to invoke any method on any object it can navigate to at runtime. You must consider the security implications of this type of evaluation.
     */
    public static function expressionForSelector(Expression $target, string $selector, ?ArrayClass $arguments = null): Expression
    {
        return FunctionExpression::functionWithSelector($target, $selector, $arguments);
    }

    public static function expressionForConditional(Predicate $predicate, Expression $trueExpression, Expression $falseExpression): Expression
    {
        return new TernaryExpression($predicate, $trueExpression, $falseExpression);
    }

    public static function expressionForVariableNameAssignment(string $name, Expression $expression): Expression
    {
        return new VariableAssignmentExpression(new VariableExpression($name), $expression);
    }

    public static function expressionForSymbolicString(string $string): Expression
    {
        return new SymbolicExpression($string);
    }

    /**
     * @internal
     */
    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return $this;
    }

    /**
     * Evaluates an expression using a given object and context.
     * @param mixed|null $object The object against which the expression is evaluated.
     * @param Dictionary<mixed>|null $context A dictionary that the expression can use to store a temporary state for one predicate evaluation. Can be null.
     * Note that context is mutable, and that it can only be accessed during the evaluation of the expression.
     * You must not attempt to retain it for use elsewhere.
     * @return mixed The evaluated object.
     */
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        return null;
    }

    /** @internal */
    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        if ($flags & PredicateVisitorFlags::expressions) {
            $visitor->visitPredicateExpression($this);
        }
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->predicateFormat;
    }
}
