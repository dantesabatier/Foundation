<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Set;

final class Paid extends ObjectClass
{
    /**
     * @param float $amount The amount paid.
     */
    public function __construct(public float $amount)
    {
    }
}

/**
 * Tests for the evaluation trace behind Predicate::$debugDefault.
 *
 * Regression guards:
 *  - SetExpression mutates its left operand in place (formUnion, subtract,
 *    formIntersection) and logged it afterwards, so the operand position showed the
 *    result: the line read "[1, 2, 3, 4] unionSet [3, 4] => [1, 2, 3, 4]" and there was no
 *    way to see what the left side had held. The operand is captured before the mutation
 *    now;
 *  - ConstantValueExpression and AggregateExpression emitted their value without the "=>"
 *    every other line uses, which broke the uniform "who → what → result" reading.
 *
 * The trace is testable at all because it goes through $debugHandler; it previously went
 * straight to error_log(), which a test can only reach indirectly.
 */
final class PredicateDebugTest extends TestCase
{
    /** @var list<string> $lines The trace collected during one test. */
    private array $lines = [];

    protected function setUp(): void
    {
        $this->lines = [];
        Predicate::$debugHandler = function (string $line): void {
            $this->lines[] = $line;
        };
        Predicate::$debugDefault = true;
    }

    protected function tearDown(): void
    {
        // Leaving either of these set would trace every later test in the run.
        Predicate::$debugDefault = false;
        Predicate::$debugHandler = null;
    }

    private function trace(): string
    {
        return implode("\n", $this->lines);
    }

    public function testTheHandlerReceivesTheTraceInsteadOfTheErrorLog(): void
    {
        Predicate::format("n == 3")->evaluate(new Dictionary(["n" => 3]));

        $this->assertNotSame([], $this->lines);
        foreach ($this->lines as $line) {
            $this->assertStringStartsWith("Foundation: ", $line);
        }
    }

    public function testNothingIsTracedWhenDebuggingIsOff(): void
    {
        Predicate::$debugDefault = false;

        Predicate::format("n == 3")->evaluate(new Dictionary(["n" => 3]));

        $this->assertSame([], $this->lines);
    }

    public function testEveryLineReportsItsResult(): void
    {
        // The uniform shape: each line ends in "=> <result>", including the constant and
        // aggregate expressions that used to print a bare value.
        Predicate::format("n IN {1, 3, 5}")->evaluate(new Dictionary(["n" => 3]));

        foreach ($this->lines as $line) {
            $this->assertStringContainsString("=>", $line, $line);
        }
    }

    public function testASetExpressionReportsTheOperandItStartedWith(): void
    {
        $object = new Dictionary(["a" => new ArrayClass([1, 2]), "b" => new ArrayClass([3, 4])]);

        Predicate::format("1 IN a UNION b")->evaluate($object);

        $union = array_values(array_filter($this->lines, static fn(string $line): bool => str_contains($line, "unionSet")));
        $this->assertCount(1, $union);
        // The left operand as it was, then the right, then the union — not the result three
        // times over.
        $this->assertStringContainsString("[1, 2] [3, 4] => [1, 2, 3, 4]", $union[0]);
    }

    public function testACompoundPredicateBracketsItsSubpredicates(): void
    {
        $object = new Dictionary(["n" => 3, "s" => "hello"]);

        Predicate::format("n == 3 AND s BEGINSWITH \"he\"")->evaluate($object);

        $trace = $this->trace();
        $this->assertStringContainsString("and (2 subpredicates)", $trace, "the compound announces itself");
        $this->assertStringContainsString("and => true", $trace, "and reports its own result");
    }

    public function testSubpredicateLinesAreIndentedUnderTheCompound(): void
    {
        $object = new Dictionary(["n" => 3, "s" => "hello"]);

        Predicate::format("n == 3 AND s BEGINSWITH \"he\"")->evaluate($object);

        $opening = $this->lines[0];
        $this->assertStringContainsString("subpredicates", $opening);
        $this->assertSame("Foundation: <", substr($opening, 0, 13), "the compound sits at depth zero");

        $indented = array_values(array_filter($this->lines, static fn(string $line): bool => str_starts_with($line, "Foundation:   ")));
        $this->assertNotSame([], $indented, "the subpredicates are indented beneath it");
    }

    public function testTheTraceNamesTheAccessorEachKeyPathWentThrough(): void
    {
        // The distinction that matters when a collection operator misresolves: which of the
        // two accessors a segment took.
        $object = new Dictionary(["payments" => new Set([new Paid(100.0), new Paid(200.0)])]);

        Predicate::format("payments.amount.@sum == 300")->evaluate($object);

        $trace = $this->trace();
        $this->assertStringContainsString("valueForKey(amount)", $trace);
        $this->assertStringContainsString("valueForKeyPath(@sum)", $trace);
    }

    public function testTheTraceCarriesTheTypeOfEachOperand(): void
    {
        // "(Number)300 = (float)300" is what makes a strict-type mismatch visible; a bare
        // "300 = 300" would not.
        Predicate::format("n == 3")->evaluate(new Dictionary(["n" => 3]));

        $comparison = array_values(array_filter($this->lines, static fn(string $line): bool => str_contains($line, "equalTo")));
        $this->assertCount(1, $comparison);
        $this->assertMatchesRegularExpression("/\(\w+\)/", $comparison[0], "each operand is prefixed with its type");
    }
}
