# Sabatier Foundation

**Foundation** is the base layer of the PHP 8.5+ **Sabatier SDK**. The SDK is formed by three sibling frameworks — Foundation, [CoreData](https://github.com/dantesabatier/CoreData) and [Service](https://github.com/dantesabatier/Service) — combining system primitives, managed persistence and application infrastructure for server-side PHP development. Foundation supplies the primitives the other two build on, and stands on its own: it has no dependency on either.

A port of Apple's **Foundation** framework to PHP — faithful to its design, not to its syntax.

Collections modelled on Swift's standard library, key-value coding and observing, an `NSPredicate`-compatible filtering language, file management, and a `URLSession`-style HTTP/FTP/WebSocket client. Built on property hooks, asymmetric visibility, enums and the pipe operator, with no external runtime dependencies.

Requires **PHP 8.5+**.

## Contents

- [Installation](#installation)
- [Collections](#collections)
- [Key-Value Coding](#key-value-coding)
- [Key-Value Observing](#key-value-observing)
- [Predicates](#predicates)
- [Networking](#networking)
- [Dates](#dates)
- [Files](#files)
- [Serialization](#serialization)
- [Two conventions that will surprise you](#two-conventions-that-will-surprise-you)
- [Static analysis](#static-analysis)

## Installation

```bash
composer require sabatier/foundation
```

## Collections

The protocol stack mirrors Swift's standard library — `Sequence → Collection → BidirectionalCollection → MutableCollection → RangeReplaceableCollection` — with three concrete types: `ArrayClass` (ordered, random-access), `Dictionary` (key-value) and `Set` (unordered, unique).

```php
use Sabatier\Foundation\ArrayClass;

$array = new ArrayClass([1, 2, 3, 4, 5]);
$evenSquares = $array
    ->filter(fn(int $n): bool => $n % 2 === 0)
    ->map(fn(int $n): int => $n * $n);

echo $evenSquares->join(", "); // "4, 16"
```

`Set` carries the full set algebra:

```php
use Sabatier\Foundation\Set;

$a = new Set([1, 2, 3]);
$b = new Set([3, 4]);

$a->union($b);        // [1, 2, 3, 4]
$a->intersection($b); // [3]
```

Sorting is descriptor-driven, like `NSSortDescriptor`:

```php
use Sabatier\Foundation\SortDescriptor;

$byAgeDescending = $people->sorted([new SortDescriptor("age", false)]);
```

## Key-Value Coding

Dynamic property access by key or key path, including the collection operators:

```php
$employees->valueForKeyPath("@sum.salary");             // 285000
$employees->valueForKeyPath("@avg.salary");             // 95000
$employees->valueForKeyPath("@count");                  // 3
$employees->valueForKeyPath("@unionOfObjects.name");    // [Ada, Grace, Alan]
```

`@sum`, `@avg`, `@count`, `@min`, `@max`, `@median`, `@unionOfObjects`, `@distinctUnionOfObjects` and friends are supported. A key path takes a **single** collection operator; see [PREDICATES.md](PREDICATES.md#known-limits) for the details.

## Key-Value Observing

```php
use Sabatier\Foundation\KeyValueObservingOptions;

$account->addObserver($auditor, "balance", KeyValueObservingOptions::old | KeyValueObservingOptions::new);
$account->setValueForKey(250.0, "balance");
// $auditor->observeValue("balance", $account, $change) receives old = 100.0, new = 250.0
```

The observer implements `observeValue(string $keyPath, mixed $object, KeyValueObservedChange $change, mixed $context = null)`. There is also a closure-based form:

```php
$account->observe("balance", KeyValueObservingOptions::new, function (mixed $object, KeyValueObservedChange $change): void {
    echo $change->newValue;
});
```

One change sends one notification. Pass `KeyValueObservingOptions::prior` to receive the pre-change notification as well, and `KeyValueObservingOptions::initial` to be primed with the current value at registration.

## Predicates

A port of `NSPredicate`: ~47 classes covering comparison, compound and expression nodes, with a recursive-descent parser for the format-string grammar.

```php
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Predicates\Predicate;

$predicate = Predicate::format("dept == %s AND salary > %f", new ArrayClass(["eng", 80000.0]));
$engineers = $employees->filtered($predicate);
```

Note `filtered(Predicate)` alongside `filter(Closure)` — the predicate form is what a store can lower to SQL, which the closure form cannot be.

To test a single object rather than filter a collection, call `evaluate()`:

```php
$predicate->evaluate($employee); // true
```

A predicate written as a template resolves its `$VARIABLE` tokens at evaluation time, either through `withSubstitutionVariables()` or by passing the dictionary to `evaluate()` directly:

```php
$template = Predicate::format("dept == \$DEPT");

$template->evaluate($employee, new Dictionary(["\$DEPT" => "eng"]));
$template->withSubstitutionVariables(new Dictionary(["\$DEPT" => "eng"]))->evaluate($employee);
```

Two different things are being looked up here, and only one of them takes a `$`. `dept` is a key path, read off the object under evaluation. `$DEPT` is a substitution variable, and the `$` is part of its name — so **the key in the substitution dictionary keeps it**: `["$DEPT" => "eng"]`, not `["DEPT" => "eng"]`. A key written without the `$` matches no token, so the variable stays unresolved: the predicate reads back as `dept = null` and answers `false` rather than raising.

Options survive the round trip, so a predicate reads back the way it was written:

```php
Predicate::format("name BEGINSWITH[c] %s", new ArrayClass(["a"]))->predicateFormat;
// name BEGINSWITH[c] 'a'
```

**The grammar is documented in full in [PREDICATES.md](PREDICATES.md)** — operators, modifiers (`ANY`/`SOME`/`ALL`/`NONE`), aggregates, `SUBQUERY`, the function library, and the places where the language stops. Two points worth knowing up front:

- **`LIKE` expands no wildcard.** Neither `*`/`?` nor SQL's `%`/`_`. Every character in the pattern is a literal, which is what makes an exact match on a value holding a literal `%` work. Use `BEGINSWITH`/`CONTAINS`/`ENDSWITH` for prefix and substring searches, and `MATCHES` for a regular expression.
- **`BETWEEN` is inclusive at both ends**, matching SQL rather than the framework's own half-open `in_range()`.

## Networking

A `URLSession`-style client — ~79 classes over CURL — with data, download, upload, stream and WebSocket tasks, cookie storage, response caching and challenge-based authentication.

```php
use Sabatier\Foundation\Error;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLResponse;
use Sabatier\Foundation\Networking\URLSession;

$session = URLSession::shared();
$task = $session->dataTaskWithURL(new URL("https://example.com"), function (?string $data, ?URLResponse $response, ?Error $error): void {
    if ($error !== null) {
        echo $error->localizedDescription;
        return;
    }
    if ($response instanceof HTTPURLResponse) {
        echo $response->statusCode; // 200
    }
});
$task->resume();
```

Tasks are created suspended and started with `resume()`, exactly as in Foundation. The same session vends the other task types:

```php
$session->downloadTaskWithURL($url, $handler);          // to a file
$session->uploadTaskWithRequest($request, $fileURL);    // from a file
$session->webSocketTaskWithURL($url);                   // send()/receive()
```

Requests carry their own cache policy and timeout, and a session is configured through `URLSessionConfiguration`:

```php
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLRequestCachePolicy;

$request = new URLRequest($url, URLRequestCachePolicy::reloadIgnoringCacheData, timeoutInterval: 30.0);
```

For progress, redirection, per-task authentication and incremental data, implement `URLSessionDelegate` and its sub-protocols (`URLSessionDataDelegate`, `URLSessionDownloadDelegate`, `URLSessionWebSocketDelegate`) and pass the delegate to the session.

## Dates

`Date` stores a true **CFAbsoluteTime**: seconds since 00:00:00 UTC on 1 January **2001**, not 1970.

```php
use Sabatier\Foundation\Date;

$now = new Date();
$fromUnix = Date::dateWithTimeIntervalSince1970(time());
$fromAbsolute = Date::dateWithTimeIntervalSinceReferenceDate($interval);
$later = Date::dateWithTimeIntervalSinceNow(3600);
```

Pick the factory that names the epoch of the value you hold. Anything Unix-based — `time()`, `strtotime()`, `filemtime()`, `DateTime::getTimestamp()`, a numeric column in a database — goes through `dateWithTimeIntervalSince1970()`. A raw interval fed to the wrong factory lands 31 years off.

**Elapsed time is not measured with dates.** Benchmarks, timeouts and heartbeats use the monotonic clock:

```php
use Sabatier\Foundation\ProcessInfo;

$start = ProcessInfo::processInfo()->systemUptime;
// ...
$elapsed = ProcessInfo::processInfo()->systemUptime - $start;
```

## Files

```php
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SearchPathDomainMask;

$manager = FileManager::default();
$documents = $manager->url(SearchPathDirectory::documentsDirectory, SearchPathDomainMask::user);
$configuration = $documents->appendingPathComponent("config.plist");

if (!$manager->fileExists($configuration->path)) {
    // ...
}
```

`FileManager` mirrors `NSFileManager` — URL-based paths, attributes, directory enumeration and delegate hooks — with `FileHandle` for stream-level access and `URLResourceValues` for resource keys.

## Serialization

Property lists round-trip in XML and binary form:

```php
use Sabatier\Foundation\PropertyListSerialization;

$xml = PropertyListSerialization::data($plist);
$restored = PropertyListSerialization::propertyList($xml);
```

`PropertyListSerialization::writePropertyList()` and `propertyListWithURL()` work against a `URL` directly. Every collection type also implements `JsonSerializable`.

An object graph archives through `KeyedArchiver`, which goes through PHP's own `serialize()`:

```php
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\KeyedArchiver;
use Sabatier\Foundation\KeyedUnarchiver;

$data = KeyedArchiver::archivedData($graph);
$restored = KeyedUnarchiver::unarchiveTopLevelObjectWithData($data);
```

**Decoding instantiates whatever classes the archive names**, which is a way to construct arbitrary objects and run their destructors. For anything this process did not write, name the classes it is allowed to build:

```php
KeyedUnarchiver::unarchiveTopLevelObjectWithData($data, new ArrayClass([Dictionary::class, Date::class]));
```

Anything outside the list decodes to `__PHP_Incomplete_Class` instead of being constructed. This is what secure coding is here: PHP applies the restriction in the engine, before anything is built, so there is no marker protocol to conform to.

## Two conventions that will surprise you

Both are deliberate, both differ from what a PHP developer would assume, and both are cheaper to read here than to discover in a debugger.

### `new Date()` takes no arguments

```php
new Date();              // the current instant
new Date(1000000000);    // InternalInconsistencyException
```

PHP would silently ignore the extra argument, so it is guarded: passing anything raises, and the message names the factory to use instead. Construct from a value with `Date::dateWithTimeIntervalSince1970()` or one of the other factories above.

### `$hash` is identity, never value

Identity and conceptual equality are separate axes, framework-wide.

```php
$a = new Number(5);
$b = new Number(5);

$a->isEqual($b);          // true  — conceptually equal
$a->hash === $b->hash;    // false — different objects
```

`$hash` answers `spl_object_id()`, and no subclass overrides it — not `Number`, `Date`, `URL` or `UUID`. It tells you *which object this is*, and nothing about what it holds. Conceptual equality lives only in `isEqual(mixed)` / `compare(mixed)`. The parameter is `mixed` deliberately: any value may be asked against any other, and incomparable values answer `false` rather than raising.

So **the familiar rule that equal objects must produce equal hashes does not hold here, and is not meant to.** Two `Number`s holding 5 are equal and remain distinguishable as objects; that is the point of keeping the two axes apart, not an oversight to be fixed.

Nothing depends on the rule, because nothing in the framework looks up by hash. `Set`, `Dictionary` and `ArrayClass` deduplicate and compare element-wise through `isEqual`, so any type that defines its own equality participates in deep comparison for free — including your own. When you write a class that redefines equality, override `isEqual`/`compare` and leave `$hash` alone.

## Static analysis

The collections are annotated with `@template` tags, and a bundled Psalm plugin extends return-type inference for `compactMap()` so a mapped collection keeps its element type. Correctness is enforced through Psalm at level 4 and the PHPUnit suite in [tests/](tests), which covers 80% of the framework's lines across 96 files.

See [CONTRIBUTING.md](CONTRIBUTING.md) for how to run both, and [CONVENTIONS.md](CONVENTIONS.md) for the conventions a change is expected to follow — each with the search that finds a violation, since the analysers catch almost none of them.

## License

MIT. See [LICENSE.md](LICENSE.md).
