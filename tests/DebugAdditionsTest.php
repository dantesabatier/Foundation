<?php
/** @noinspection PhpDeprecationInspection */

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Closure;
use Error;
use Exception;
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sabatier\Foundation\EscapeSequenceColor;
use Sabatier\Foundation\EscapeSequenceTextAttribute;
use Sabatier\Foundation\InternalInconsistencyException;
use stdClass;
use Stringable;
use Throwable;

use function Sabatier\Foundation\class_name;
use function Sabatier\Foundation\cli_log;
use function Sabatier\Foundation\debuglog;
use function Sabatier\Foundation\escape_sequence;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\get_calling_class;
use function Sabatier\Foundation\human_readable_bytes;
use function Sabatier\Foundation\human_readable_plural;
use function Sabatier\Foundation\human_readable_time;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\invalid_mutation;
use function Sabatier\Foundation\pluralize;
use function Sabatier\Foundation\request_concrete_implementation;
use function Sabatier\Foundation\typeof;
use function Sabatier\Foundation\unimplemented;
use function Sabatier\Foundation\unsafe_value;
use function Sabatier\Foundation\unsupported;

/** @internal */
final class DebugCallTarget
{
    public function callingClass(): ?string
    {
        return get_calling_class();
    }
}

/** @internal */
final class DebugCaller
{
    public function call(DebugCallTarget $target): ?string
    {
        return $target->callingClass();
    }
}

/** Exercises formatting, diagnostics, and error conversion helpers. */
final class DebugAdditionsTest extends TestCase
{
    /** @param Closure(): mixed $operation */
    private function exceptionFrom(Closure $operation): InternalInconsistencyException
    {
        try {
            $operation();
        } catch (InternalInconsistencyException $exception) {
            return $exception;
        }
        self::fail("Expected an InternalInconsistencyException");
    }

    /** @param Closure(): mixed $operation */
    private function throwableFrom(Closure $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $throwable) {
            return $throwable;
        }
        self::fail("Expected a Throwable");
    }

    private function inferredFatalError(): never
    {
        fatal_error("inferred");
    }

    public function testDebugLogWritesOneLine(): void
    {
        $this->expectOutputString("message\n");
        debuglog("message");
    }

    /** @psalm-suppress DeprecatedFunction */
    public function testDeprecatedCLILogReportsAndWritesTheMessage(): void
    {
        $deprecation = null;
        set_error_handler(static function (int $severity, string $message) use (&$deprecation): bool {
            if ($severity !== E_USER_DEPRECATED) {
                return false;
            }
            $deprecation = $message;
            return true;
        });
        try {
            $this->expectOutputString("message\n");
            cli_log("message");
        } finally {
            restore_error_handler();
        }
        $this->assertSame("Sabatier\\Foundation\\cli_log() is deprecated, use debuglog() instead", $deprecation);
    }

    public function testEscapeSequenceUsesDefaultAndExplicitAttributes(): void
    {
        $this->assertSame("\e[0;37mtext\e[0m", escape_sequence("text"));
        $this->assertSame("\e[1;31;44mtext\e[0m", escape_sequence("text", EscapeSequenceTextAttribute::bold, EscapeSequenceColor::red, EscapeSequenceColor::blue));
    }

    public function testTypeOfUsesPHPDebugTypeNames(): void
    {
        $this->assertSame("null", typeof(null));
        $this->assertSame("int", typeof(1));
        $this->assertSame("array", typeof([]));
        $this->assertSame(stdClass::class, typeof(new stdClass()));
    }

    public function testHumanReadableValueFormatsPrimitiveAndStructuredValues(): void
    {
        $this->assertSame("value", human_readable_value("value"));
        $this->assertSame("12", human_readable_value(12));
        $this->assertSame("1.5", human_readable_value(1.5));
        $this->assertSame("true", human_readable_value(true));
        $this->assertSame("false", human_readable_value(false));
        $this->assertSame("null", human_readable_value(null));
        $this->assertSame("[0: one, key: [nested: 2]]", human_readable_value(["one", "key" => ["nested" => 2]]));
    }

    public function testHumanReadableValueFormatsObjectsEnumsAndResources(): void
    {
        $stringable = new class implements Stringable {
            #[Override]
            public function __toString(): string
            {
                return "fixture";
            }
        };
        $this->assertSame("fixture", human_readable_value($stringable));
        $this->assertSame(EscapeSequenceColor::class . "::red", human_readable_value(EscapeSequenceColor::red));
        $this->assertSame(stdClass::class, human_readable_value(new stdClass()));

        $resource = fopen("php://memory", "r");
        $this->assertIsResource($resource);
        try {
            $this->assertSame("resource (stream)", human_readable_value($resource));
        } finally {
            fclose($resource);
        }
    }

    public function testHumanReadableValueStopsAtTheMaximumDepth(): void
    {
        $value = ["root" => ["child" => ["leaf" => 1]]];
        $this->assertSame("[root: [child: [max depth]]]", human_readable_value($value, maxDepth: 2));
        $this->assertSame("[max depth]", human_readable_value($value, maxDepth: 0));
    }

    /** @throws Exception */
    public function testHumanReadableTimeFormatsEverySupportedUnit(): void
    {
        $seconds = 31_536_000 + 2_592_000 + 86_400 + 3_600 + 60 + 1.25;
        $this->assertSame("1 year, 1 month, 1 day, 1 hour, 1 minute, 1 second, 250 milliseconds", human_readable_time($seconds));
        $this->assertSame("0 milliseconds", human_readable_time(0));
        $this->assertSame("1 millisecond", human_readable_time(0.001));
    }

    public function testHumanReadableBytesSelectsAndClampsUnits(): void
    {
        $this->assertSame("0 B", human_readable_bytes(0));
        $this->assertSame("1,023 B", human_readable_bytes(1023));
        $this->assertSame("1.00 KB", human_readable_bytes(1024));
        $this->assertSame("1.50 KB", human_readable_bytes(1536));
        $this->assertSame("1,024.00 PB", human_readable_bytes(1024 ** 6));
    }

    /** @psalm-suppress DeprecatedFunction */
    public function testPluralizationUsesTheRequestedCount(): void
    {
        $this->assertSame("apple", pluralize("apple", 1));
        $this->assertSame("apples", pluralize("apple", 0));
        $this->assertSame("apples", pluralize("apple", 2));
        $this->assertSame("apples", pluralize("apple", 1.5));
        $this->assertSame("item", human_readable_plural("item", 1));
        $this->assertSame("items", human_readable_plural("item", 2));
    }

    public function testFatalErrorUsesExplicitAndInferredLocations(): void
    {
        $exception = $this->exceptionFrom(fn(): never => fatal_error("failure", "fixture.php", 42));
        $this->assertSame("failure", $exception->getMessage());
        $this->assertSame("fixture.php", $exception->getFile());
        $this->assertSame(42, $exception->getLine());
        $this->assertSame(E_ERROR, $exception->getSeverity());

        $line = __LINE__ + 2;
        try {
            $this->inferredFatalError();
        } catch (InternalInconsistencyException $inferredException) {
        }
        $this->assertSame(__FILE__, $inferredException->getFile());
        $this->assertSame($line, $inferredException->getLine());
    }

    public function testFatalErrorConvenienceFunctionsDescribeTheFailure(): void
    {
        $object = new stdClass();
        $this->assertSame("stdClass save is not yet implemented", $this->exceptionFrom(fn(): never => unimplemented($object, "save"))->getMessage());
        $this->assertSame("Fixture save is not supported", $this->exceptionFrom(fn(): never => unsupported("Fixture", "save"))->getMessage());
        $this->assertSame("stdClass save requires a subclass implementation", $this->exceptionFrom(fn(): never => request_concrete_implementation($object, "save"))->getMessage());
        $this->assertSame("attempting to mutate an immutable object", $this->exceptionFrom(invalid_mutation(...))->getMessage());
    }

    public function testUnsafeValueReturnsValuesConvertsWarningsAndRestoresTheHandler(): void
    {
        $previousHandler = static fn(int $severity, string $message, string $file, int $line): bool => true;
        set_error_handler($previousHandler);
        try {
            $this->assertSame(42, unsafe_value(static fn(): int => 42));
            foreach ([E_USER_WARNING, E_USER_NOTICE] as $severity) {
                try {
                    unsafe_value(static fn(): bool => trigger_error("converted", $severity));
                    self::fail("Expected the warning to be converted");
                } catch (InternalInconsistencyException $exception) {
                    $this->assertSame("converted", $exception->getMessage());
                }
            }

            $blockError = new Error("block error");
            $this->assertSame($blockError, $this->throwableFrom(static function () use ($blockError): void {
                unsafe_value(static fn(): never => throw $blockError);
            }));

            $runtimeException = new RuntimeException("block failed");
            $this->assertSame($runtimeException, $this->throwableFrom(static function () use ($runtimeException): void {
                unsafe_value(static fn(): never => throw $runtimeException);
            }));

            $currentHandler = set_error_handler(static fn(int $severity, string $message, string $file, int $line): bool => false);
            $this->assertSame($previousHandler, $currentHandler);
            restore_error_handler();
        } finally {
            restore_error_handler();
        }
    }

    public function testClassNameOptionallyExtractsTheNamespace(): void
    {
        $namespace = null;
        $this->assertSame("DebugCaller", class_name(DebugCaller::class, $namespace));
        $this->assertSame(__NAMESPACE__, $namespace);

        $namespace = null;
        $this->assertSame(stdClass::class, class_name(stdClass::class, $namespace));
        $this->assertNull($namespace);
    }

    public function testCallingClassSkipsTheImmediateReceiver(): void
    {
        $caller = new DebugCaller();
        $this->assertSame(DebugCaller::class, $caller->call(new DebugCallTarget()));
    }
}
