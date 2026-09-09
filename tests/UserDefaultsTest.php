<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;

use const Sabatier\Foundation\BytesPerKilobyte;
use const Sabatier\Foundation\USER_DEFAULTS_SIZE_LIMIT;

/**
 * Tests for src/UserDefaults.php.
 *
 * Regression guard:
 *  - the double accessor was spelled doble, the Spanish for "double", while its own
 *    docblock described a "double value" and its setter was already setDouble. Renamed
 *    before the package is published, since a public method cannot be corrected afterwards
 *    without a deprecation cycle. It had no caller anywhere in the Sabatier projects.
 *
 * Each test uses a suite named after the process so a run never sees another run's values,
 * and clears what it wrote.
 */
final class UserDefaultsTest extends TestCase
{
    private UserDefaults $defaults;
    private string $suiteName;
    /** @var list<string> $written The keys to clear in tearDown. */
    private array $written = [];

    #[Override]
    protected function setUp(): void
    {
        $this->suiteName = sprintf("foundation-tests-%d", getmypid());
        $this->defaults = new UserDefaults($this->suiteName);
        $this->written = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->written as $key) {
            $this->defaults->removeObject($key);
        }
        $this->assertTrue($this->defaults->synchronize());
    }

    private function write(string $key): string
    {
        $this->written[] = $key;
        return $key;
    }

    public function testDoubleRoundTripsThroughItsSetter(): void
    {
        $this->defaults->setDouble(2.5, $this->write("amount"));

        $this->assertSame(2.5, $this->defaults->double("amount"));
    }

    public function testDoubleCoercesTheValuesItsContractNames(): void
    {
        $this->defaults->setBool(true, $this->write("flag"));
        $this->defaults->setInteger(2, $this->write("count"));
        $this->defaults->setObject("123.4", $this->write("text"));
        $this->defaults->setObject("abc", $this->write("words"));

        $this->assertSame(1.0, $this->defaults->double("flag"), "true becomes 1.0");
        $this->assertSame(2.0, $this->defaults->double("count"), "an integer becomes the equivalent double");
        $this->assertSame(123.4, $this->defaults->double("text"), "a numeric string becomes its value");
        $this->assertSame(0.0, $this->defaults->double("words"), "anything else becomes 0.0");
    }

    public function testDoubleAndFloatAgree(): void
    {
        // Both exist because Cocoa has floatForKey: and doubleForKey:; in PHP they are the same type, so they must not disagree.
        $this->defaults->setDouble(2.5, $this->write("amount"));

        $this->assertSame($this->defaults->float("amount"), $this->defaults->double("amount"));
    }

    public function testTypedAccessorsRoundTrip(): void
    {
        $this->defaults->setObject("hola", $this->write("greeting"));
        $this->defaults->setInteger(42, $this->write("answer"));
        $this->defaults->setFloat(1.5, $this->write("ratio"));
        $this->defaults->setBool(true, $this->write("enabled"));
        $this->defaults->setURL(new URL("https://example.com"), $this->write("endpoint"));

        $this->assertSame("hola", $this->defaults->string("greeting"));
        $this->assertSame(42, $this->defaults->integer("answer"));
        $this->assertSame(1.5, $this->defaults->float("ratio"));
        $this->assertTrue($this->defaults->bool("enabled"));
        $this->assertSame("https://example.com", $this->defaults->url("endpoint")?->absoluteString);
    }

    public function testCollectionsRoundTrip(): void
    {
        $this->defaults->setObject(new ArrayClass([1, 2, 3]), $this->write("list"));
        $this->defaults->setObject(new Dictionary(["k" => "v"]), $this->write("map"));

        $this->assertSame([1, 2, 3], $this->defaults->array("list")?->array);
        $this->assertSame("v", $this->defaults->dictionary("map")?->offsetGet("k"));
    }

    public function testStringArrayRequiresEveryElementToBeAString(): void
    {
        $this->defaults->setObject(new ArrayClass(["one", "two"]), $this->write("strings"));
        $this->defaults->setObject(new ArrayClass(["one", 2]), $this->write("mixed"));

        $this->assertSame(["one", "two"], $this->defaults->stringArray("strings")?->array);
        $this->assertNull($this->defaults->stringArray("mixed"));
        $this->assertNull($this->defaults->stringArray("absent"));
    }

    public function testRegisterSuppliesMissingValuesWithoutReplacingStoredValues(): void
    {
        $existing = $this->write("existing");
        $fallback = $this->write("fallback");
        $this->defaults->setObject("stored", $existing);
        $registered = new Dictionary([$existing => "registered", $fallback => "default"]);

        $this->defaults->register($registered);
        $representation = $this->defaults->dictionaryRepresentation();

        $this->assertSame("stored", $this->defaults->string($existing));
        $this->assertSame("default", $this->defaults->string($fallback));
        $this->assertSame("stored", $representation[$existing]);
        $this->assertSame("default", $representation[$fallback]);
    }

    public function testPersistentValueOverridesARegisteredDefaultUntilItIsRemoved(): void
    {
        $key = $this->write("appearance");
        $this->defaults->register(new Dictionary([$key => "light"]));
        $this->defaults->setObject("dark", $key);

        $this->assertSame("dark", $this->defaults->string($key));

        $this->defaults->removeObject($key);

        $this->assertSame("light", $this->defaults->string($key));
    }

    public function testRegisteredDefaultsAreNotLoadedByAFreshInstance(): void
    {
        $registeredKey = $this->write("registered-only");
        $persistentKey = $this->write("persistent");
        $this->defaults->register(new Dictionary([$registeredKey => "transient"]));
        $this->defaults->setObject("stored", $persistentKey);

        $fresh = new UserDefaults($this->suiteName);

        $this->assertNull($fresh->string($registeredKey));
        $this->assertSame("stored", $fresh->string($persistentKey));
    }

    public function testPersistentDomainCanBeSetAndRemoved(): void
    {
        $this->defaults->setPersistentDomain(new Dictionary(["theme" => "dark"]), $this->suiteName);

        $this->assertSame("dark", $this->defaults->persistentDomain($this->suiteName)["theme"]);

        $this->defaults->removePersistentDomain($this->suiteName);

        $this->assertSame(0, $this->defaults->persistentDomain($this->suiteName)->count);
    }

    public function testSettingPersistentDomainReplacesItsPreviousContents(): void
    {
        $this->defaults->setPersistentDomain(new Dictionary(["stale" => "old"]), $this->suiteName);
        $this->defaults->setPersistentDomain(new Dictionary(["current" => "new"]), $this->suiteName);

        $domain = $this->defaults->persistentDomain($this->suiteName);

        $this->assertNull($domain["stale"]);
        $this->assertSame("new", $domain["current"]);

        $this->defaults->removePersistentDomain($this->suiteName);
    }

    public function testSizeLimitNotificationUsesKilobytes(): void
    {
        /** @var ArrayClass<bool> $notifications */
        $notifications = new ArrayClass();
        $center = NotificationCenter::default();
        $receiveNotification = function () use ($notifications): void {
            $notifications->append(true);
        };
        $observer = $center->addObserverForName(UserDefaults::sizeLimitExceededNotification, $this->defaults, $receiveNotification);

        try {
            $this->defaults->setObject("small", $this->write("small"));
            $this->assertCount(0, $notifications);

            $this->defaults->setObject(str_repeat("x", (USER_DEFAULTS_SIZE_LIMIT + 1) * BytesPerKilobyte), $this->write("large"));
            $this->assertCount(1, $notifications);
        } finally {
            $center->removeObserver($observer);
        }
    }

    public function testStandardReturnsTheSharedDefaultsInstance(): void
    {
        $this->assertSame(UserDefaults::standard(), UserDefaults::standard());
    }

    public function testInstancesOfTheSameSuiteSharePersistentValues(): void
    {
        $key = $this->write("shared");
        $other = new UserDefaults($this->suiteName);

        $this->defaults->setObject("value", $key);

        $this->assertSame("value", $other->string($key));
    }

    public function testRemovingAndReaddingASuiteKeepsItsPersistentValues(): void
    {
        $key = $this->write("retained");
        $this->defaults->setObject("value", $key);

        $this->defaults->removeSuite($this->suiteName);
        $this->defaults->addSuite($this->suiteName);

        $this->assertSame("value", $this->defaults->string($key));
    }

    public function testAMissingKeyReturnsTheDocumentedDefault(): void
    {
        $this->assertNull($this->defaults->string("absent"));
        $this->assertSame(0, $this->defaults->integer("absent"));
        $this->assertSame(0.0, $this->defaults->float("absent"));
        $this->assertSame(0.0, $this->defaults->double("absent"));
        $this->assertFalse($this->defaults->bool("absent"));
        $this->assertNull($this->defaults->array("absent"));
        $this->assertNull($this->defaults->dictionary("absent"));
        $this->assertNull($this->defaults->url("absent"));
    }

    public function testRemoveObjectClearsTheValue(): void
    {
        $this->defaults->setObject("hola", $this->write("greeting"));

        $this->defaults->removeObject("greeting");

        $this->assertNull($this->defaults->string("greeting"));
    }

    public function testAValueSurvivesSynchronizeAndAFreshInstance(): void
    {
        // The value has to reach disk, which is where the plist escaping matters: a string holding an ampersand used to be lost on the way out.
        $suite = sprintf("foundation-persist-%d", getmypid());
        $writer = new UserDefaults($suite);
        $writer->setObject("Ventas & Marketing", "area");
        $writer->setDouble(2.5, "amount");
        $this->assertTrue($writer->synchronize());

        $reader = new UserDefaults($suite);

        $this->assertSame("Ventas & Marketing", $reader->string("area"));
        $this->assertSame(2.5, $reader->double("amount"));

        $reader->removeObject("area");
        $reader->removeObject("amount");
        $this->assertTrue($reader->synchronize());
    }

    public function testDictionaryRepresentationCarriesEveryValue(): void
    {
        $this->defaults->setObject("hola", $this->write("greeting"));
        $this->defaults->setInteger(42, $this->write("answer"));

        $representation = $this->defaults->dictionaryRepresentation();

        $this->assertSame("hola", $representation["greeting"]);
        $this->assertSame(42, $representation["answer"]);
    }
}
