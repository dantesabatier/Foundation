<?php

namespace Sabatier\Foundation\Test;

require_once __DIR__ . "/../vendor/autoload.php";

use DateInterval;
use DateTime;
use Exception;
use JetBrains\PhpStorm\Pure;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Predicate;
use Sabatier\Foundation\Set;

class Base extends ObjectClass
{
    public string $id;
    public string $creationDate;

    public function __construct()
    {
        $this->id = spl_object_hash($this);
        $this->creationDate = date('Y-m-d H:i:s');
    }
}

class City extends Base
{
    public string $name;

    #[Pure]
    public function __construct(string $name)
    {
        parent::__construct();
        $this->name = $name;
    }
}

class Address extends Base
{
    public string $street;
    public City $city;

    #[Pure]
    public function __construct(string $street, City $city)
    {
        parent::__construct();
        $this->street = $street;
        $this->city = $city;
    }
}

class Money extends Base
{
    public float $value;

    #[Pure]
    public function __construct(float $value)
    {
        parent::__construct();
        $this->value = $value;
    }
}

class Deposit extends Base
{
    public Money $amount;

    #[Pure]
    public function __construct(Money $amount)
    {
        parent::__construct();
        $this->amount = $amount;
    }
}

class Person extends Base
{
    public string $name;
    public int $age;
    public Set $deposits;
    public Money $savings;
    public Set $addresses;
}

class Validator
{

    public function validate(string $id): bool
    {
        return !empty($id);
    }
}

final class PredicateTest extends TestCase
{

    private ?Person $person = null;
    private ?Dictionary $variables = null;

    public function testPredicateCreation(): Predicate
    {
        $format = "((%K BETWEEN \$DATES) && (SOME addresses.city.name BEGINSWITH[cd] %s) && (NONE addresses.street CONTAINS[cd] %s) && (deposits.amount.value.@sum < 1.1*3.6) && (3+3.1 >= 0.2**10)  && (2-1.1 < 1001/11.1) && ({999.6, 1001}[1] > savings.value) && (SUBQUERY(addresses, \$address, \$address.street ENDSWITH[cd] %s).@count = %i) && (1 IN {0, 1, 2, 3, 5, 8} UNION {2, 4, 6, 10}) && (%K < TERNARY(%K MATCHES[c] %s, 30, 40)) && (FUNCTION(%s, 'validate', \$ID) != false))";
        $arguments = new ArrayClass(['creationDate', 'angeles', 'melrose', 'street', 1, 'age', 'name', 'jane', new Validator()]);
        $predicate = Predicate::format($format, $arguments);
        $this->assertInstanceOf(
            Predicate::class,
            $predicate
        );
        return $predicate;
    }

    /**
     * @depends testPredicateCreation
     * @param Predicate $input
     * @return Predicate
     * @throws Exception
     */
    public function testPredicateSubstitutionVariables(Predicate $input): Predicate
    {
        $output = $input->withSubstitutionVariables($this->variables());
        $this->assertNotEquals(
            $input->predicateFormat(),
            $output->predicateFormat()
        );
        return $output;
    }

    /**
     * @depends testPredicateSubstitutionVariables
     * @param Predicate $predicate
     */
    public function testPredicateValidation(Predicate $predicate)
    {
        $this->assertTrue($predicate->evaluate($this->person(), $this->variables()));
    }

    public function person(): Person
    {
        if ($this->person === null) {
            $person = new Person();
            $person->name = 'Joe';
            $person->age = 32;
            $person->savings = new Money(999.99);
            $person->deposits = new Set([new Deposit(new Money(1.0)), new Deposit(new Money(1.1))]);
            $person->addresses = new Set([new Address('Blv. Street', new City('NY'))]);
            $this->person = $person;
        }
        return $this->person;
    }

    public function variables(): Dictionary
    {
        if ($this->variables === null) {
            $oneDay = new DateInterval('P1D');
            $tomorrow = (new DateTime())->add($oneDay);
            $yesterday = (new DateTime())->sub($oneDay);
            $this->variables = new Dictionary([
                "\$DATES" => [$yesterday->format('Y-m-d H:i:s'), $tomorrow->format('Y-m-d H:i:s')],
                "\$ID" => $this->person()->id
            ]);
        }
        return $this->variables;
    }

}
