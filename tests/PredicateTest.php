<?php

namespace Sabatier\Foundation\Test;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Expression;
use Sabatier\Foundation\Predicate;
use Sabatier\Foundation\Set;

final class PredicateTest extends TestCase
{
    public Person $person;
    /** @var Dictionary<mixed> */
    public Dictionary $variables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->person = new Person();
        $this->person->name = 'Joe';
        $this->person->age = 32;
        $this->person->savings = new Money(999.99);
        $this->person->deposits = new Set([new Deposit(new Money(1.0)), new Deposit(new Money(1.1))]);
        $this->person->addresses = new Set([new Address('Blv. Street', new City('NY'))]);
        $this->variables = new Dictionary([
            "\$DATES" => new ArrayClass([Date::distantPast(), Date::distantFuture()]),
            "\$HASH" => $this->person->hash()
        ]);
    }

    public function testCanBeCreatedFromValidArguments(): Predicate
    {
        $predicate = Predicate::format("((%K BETWEEN \$DATES) && (SOME addresses.city.name BEGINSWITH[cd] %s) && (NONE addresses.street CONTAINS[cd] %s) && (10%3 >= 1) && (deposits.amount.value.@sum < 1.1*3.6) && (3+3.1 < 0.2**10) && (2-1.1 < 1001/11.1) && ({999.6, 1001}[1] > savings.value) && (SUBQUERY(addresses, \$address, \$address.street ENDSWITH[cd] %s).@count = %i) && (1 IN {0, 1, 2, 3, 5, 8} UNION {2, 4, 6, 10}) && (%K < TERNARY(%K MATCHES[c] %s, 30, 40)) && (FUNCTION(%s, 'validate', \$HASH) != false) && (%s = %s))", new ArrayClass(['creationDate', 'Ángeles', 'Melrose', 'street', 1, 'age', 'name', 'jane', new Validator(), true, Expression::expressionForBlock(fn() => true)]));
        $this->assertInstanceOf(
            Predicate::class,
            $predicate
        );
        return $predicate;
    }

    public function testCannotBeCreatedFromInvalidArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Predicate::format('invalid');
    }

    public function testCanBeUsedAsString(): void
    {
        $this->assertEquals(
            '1 > 3',
            Predicate::format('1 > 3')
        );
    }

    /**
     * @depends testCanBeCreatedFromValidArguments
     * @param Predicate $predicate
     * @return Predicate
     * @throws Exception
     */
    public function testCanSubstituteVariables(Predicate $predicate): Predicate
    {
        $output = $predicate->withSubstitutionVariables($this->variables);
        $this->assertNotEquals(
            $predicate->predicateFormat(),
            $output->predicateFormat()
        );
        return $output;
    }

    /**
     * @depends testCanSubstituteVariables
     * @param Predicate $predicate
     * @throws Exception
     */
    public function testCanValidateObject(Predicate $predicate): void
    {
        $this->assertTrue($predicate->evaluate($this->person));
    }
}
