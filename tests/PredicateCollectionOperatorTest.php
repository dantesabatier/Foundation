<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\UndefinedKeyException;
use TypeError;

final class Employee extends ObjectClass
{
    /**
     * @param string $name The employee's name.
     * @param float $salary The employee's salary.
     */
    public function __construct(public string $name, public float $salary)
    {
    }
}

final class Department extends ObjectClass
{
    /**
     * @param Set<Employee> $employees The employees of this department.
     * @param string $city The city the department sits in.
     */
    public function __construct(public Set $employees, public string $city = "NY")
    {
    }
}

/**
 * Tests for the collection-operator half of a key path inside a predicate.
 *
 * Regression guard:
 *  - KeyPathExpression chose between valueForKey() and valueForKeyPath() on whether the key
 *    path contained a dot, and a collection operator has none: "@sum" and "@count" took the
 *    plain accessor, which maps the key over each element rather than interpreting the
 *    leading "@". So "sales.total.@sum" resolved "sales.total" to a collection of floats and
 *    then asked that collection for the key "@sum", raising "must be of type
 *    ?KeyValueCoding, float given". The same key paths always worked through
 *    valueForKeyPath() directly — only the predicate route was broken, which is how
 *    CoreData's DeleteRuleConflictDetector uses "<relationship>.@count".
 */
final class PredicateCollectionOperatorTest extends TestCase
{
    private function department(): Department
    {
        return new Department(new Set([
            new Employee("a", 100.0),
            new Employee("b", 200.0),
            new Employee("c", 300.0),
        ]));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function collectionOperatorProvider(): iterable
    {
        yield "count over a to-many relationship" => ["employees.@count == 3", true];
        yield "count that does not match" => ["employees.@count > 5", false];
        yield "sum over an attribute of the relationship" => ["employees.salary.@sum == 600", true];
        yield "average" => ["employees.salary.@avg == 200", true];
        yield "maximum" => ["employees.salary.@max == 300", true];
        yield "minimum" => ["employees.salary.@min == 100", true];
        yield "an operator on the left of a comparison" => ["employees.salary.@sum > 100", true];
    }

    #[DataProvider("collectionOperatorProvider")]
    public function testCollectionOperatorsEvaluateInsideAPredicate(string $format, bool $expected): void
    {
        $this->assertSame($expected, Predicate::format($format)->evaluate($this->department()));
    }

    public function testCollectionOperatorsOverACollectionOfScalars(): void
    {
        // The elements need not be objects: @count only counts them, and @sum reduces them
        // directly. This is the shape that made the defect obvious, since the accessor
        // demanded KeyValueCoding of each element and a number is not one.
        $object = new Dictionary(["nums" => new ArrayClass([1, 2, 3])]);

        $this->assertTrue(Predicate::format("nums.@count == 3")->evaluate($object));
        $this->assertTrue(Predicate::format("nums.@sum == 6")->evaluate($object));
    }

    public function testCountAcceptsAnyElementWhileSumNeedsNumbers(): void
    {
        // The two operators differ in what they require of the elements. @count only counts
        // them, so anything will do. @sum and @avg reduce them, so they need numbers — over
        // a collection of objects the key path has to reach the number itself
        // ("@sum.salary"), which is the form a to-many attribute takes.
        $employees = $this->department()->employees;
        $strings = new Dictionary(["c" => new ArrayClass(["a", "b"])]);
        $objects = new Dictionary(["c" => $employees]);

        $this->assertSame(3, $objects->valueForKeyPath("c.@count")?->intValue);
        $this->assertSame(2, $strings->valueForKeyPath("c.@count")?->intValue);
        $this->assertSame(600, $objects->valueForKeyPath("c.@sum.salary")?->intValue);
    }

    public function testSummingObjectsWithoutAKeyPathRaises(): void
    {
        // "@sum" straight over objects has nothing to add up; the key path must continue to
        // the attribute being summed.
        $objects = new Dictionary(["c" => $this->department()->employees]);

        $this->expectException(TypeError::class);

        $objects->valueForKeyPath("c.@sum");
    }

    public function testCollectionOperatorsNeedAFoundationCollection(): void
    {
        // A native PHP array is not KeyValueCoding, so it cannot answer a collection
        // operator at all — the value has to be an ArrayClass, Set or Dictionary. Pinned so
        // the requirement is visible rather than surfacing as a puzzling key-path error.
        $object = new Dictionary(["nums" => [1, 2, 3]]);

        $this->expectException(UndefinedKeyException::class);

        $object->valueForKeyPath("nums.@count");
    }

    public function testTheSameKeyPathAgreesWithValueForKeyPath(): void
    {
        // The predicate route and the direct KVC route must answer the same thing; they
        // disagreed because only the latter interpreted the "@".
        $department = $this->department();

        $this->assertSame(600, $department->valueForKeyPath("employees.salary.@sum")?->intValue);
        $this->assertTrue(Predicate::format("employees.salary.@sum == 600")->evaluate($department));
    }

    public function testChainedCollectionOperators(): void
    {
        // Two operators in one key path, which is what a nested to-many needs: crossing two
        // relationships yields a collection of collections, so it has to be flattened before
        // it can be reduced. @sum alone would receive a Set of Sets.
        $left = new Department(new Set([new Employee("a", 10.0), new Employee("b", 20.0)]));
        $right = new Department(new Set([new Employee("c", 30.0)]));
        $company = new Dictionary(["departments" => new Set([$left, $right])]);

        $salaries = $company->valueForKeyPath("departments.employees.@unionOfObjects.salary");

        $this->assertInstanceOf(ArrayClass::class, $salaries);
        $this->assertSame(60.0, (float)(string)$salaries->sum());
    }

    public function testReducingAFlattenedTwoLevelKeyPathIsNotSupported(): void
    {
        // Appending a reducing operator to the flattening one raises rather than summing the
        // flattened values. It predates the selector fix — verified against the previous
        // revision — and closing it means teaching the operator chain to carry the
        // intermediate collection, which is a change to how key paths are resolved rather
        // than which accessor is chosen. Recorded so the limit is visible.
        $company = new Dictionary(["departments" => new Set([new Department(new Set([new Employee("a", 10.0)]))])]);

        $this->expectException(TypeError::class);

        $company->valueForKeyPath("departments.employees.@unionOfObjects.salary.@sum");
    }

    public function testSubqueryCountInsideAPredicate(): void
    {
        $department = $this->department();

        $predicate = Predicate::format("SUBQUERY(employees, \$e, \$e.salary > 150).@count == 2");

        $this->assertTrue($predicate->evaluate($department));
    }

    /**
     * The whole grammar in one predicate, adapted from the manual bench in Raya's
     * Predicate.php: substitution variables, the ALL and NONE modifiers with case and
     * diacritic options, arithmetic, chained collection operators, SUBQUERY, an aggregate
     * index, UNION, TERNARY, FUNCTION and a block expression. It is here because no
     * single-feature suite exercises them together, and the collection-operator defect only
     * surfaced in a predicate this shape.
     */
    public function testTheFullGrammarEvaluatesTogether(): void
    {
        date_default_timezone_set("UTC");
        $subject = new Dictionary([
            "birthday" => Date::dateWithTimeIntervalSinceReferenceDate(529887685),
            "age" => 25,
            "name" => "jane",
            "departments" => new Set([$this->department()]),
            "employees" => $this->department()->employees,
        ]);
        $validator = new class {
            /**
             * @param int $hash The hash to validate.
             * @return bool Whether the hash is usable.
             */
            public function validate(int $hash): bool
            {
                return $hash > 0;
            }
        };
        $format = "(%K BETWEEN \$DATES) AND (ALL departments.city BEGINSWITH[cd] %s) AND (NONE departments.city CONTAINS[cd] %s) "
            . "AND (10 % 3 >= 1) AND (employees.salary.@sum > 1.1 * 3.6) "
            . "AND (SUBQUERY(departments, \$d, \$d.city == %s).@count == %i) "
            . "AND (1 IN {0, 1, 2} UNION {4, 6}) AND (%K < TERNARY(%K MATCHES[c] %s, 30, 40)) "
            . "AND (FUNCTION(%s, 'validate', \$HASH) != false) AND (%s == %s)";
        $arguments = new ArrayClass([
            "birthday", "NY", "Melrose", "NY", 1, "age", "name", "jane", $validator, true,
            Expression::expressionForBlock(fn(): bool => true),
        ]);

        $predicate = Predicate::format($format, $arguments);
        $this->assertNotNull($predicate);

        $variables = new Dictionary([
            "\$DATES" => new ArrayClass([Date::distantPast(), Date::distantFuture()]),
            "\$HASH" => 42,
        ]);

        $this->assertTrue($predicate->withSubstitutionVariables($variables)->evaluate($subject, $variables));
    }
}
