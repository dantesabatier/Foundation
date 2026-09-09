# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

First public release. The framework itself has been in use in private projects since 2022; the entries below record the work done to make it publishable, and are grouped by the area they touched rather than listed chronologically.

### Added

- `PREDICATES.md`, a full reference for the predicate format-string grammar: comparison and string operators, case and diacritic modifiers, aggregate operators, expressions, the function library, evaluation tracing, and the points where the language stops.
- `CONTRIBUTING.md` and this changelog.
- An evaluation trace for predicates. `Predicate::$debugDefault` turns it on; `Predicate::$debugHandler` redirects it, so a host application can route it into its own log. Nested evaluations are indented to the depth they were reached at.
- `KeyValueObservingOptions::initial`, which previously did nothing — registering an observer with it sent no notification, so an observer could not prime itself through the same path that handles later changes.
- An in-memory `FileHandle`, and `is_directory_junction()` for Windows junction points.
- Test coverage for the predicate engine, key-value observing, property list serialization, `NotificationCenter`, `UndoManager`, `Scanner` and `UserDefaults`, taking the suite from 17 files to 34 and to 700 tests. The predicate comparison operators are additionally pinned against MariaDB, since the same predicate has to answer identically whether it is evaluated in memory or lowered to SQL by a store.
- Coverage for the rest of the framework, taking the suite to 94 files and 1486 tests, and the measured line coverage from 69% to 80%. Most of it is the surface the earlier suites skipped: the collection stack the concrete types inherit rather than declare (`Set`, `IndexPath`, `Dictionary` and `CollectionDifference` each cover their algebra or their semantics but not the container underneath), the predicate expression nodes and the selector-to-operator mapping, `ObjectClass`'s introspection and key-value machinery, and the networking types that carry no transport of their own. `HTTPURLProtocolServerTest` drives a real PHP development server for the redirect chain and the upload path, on a port derived from the process id so it never collides with `URLSessionTest`.
- `CONVENTIONS.md`, a checklist of the mechanical conventions with the search that finds each violation — file layout, class and property rules, the collection idioms, when a comment earns its place, and what public API has to document. `CONTRIBUTING.md` links to it. The rest of the stack points at the same document rather than copying it.

### Fixed

**Predicates**

- `AND` now binds tighter than `OR`. `a OR b AND c` parsed as `(a OR b) AND c`, inverting the meaning of every mixed compound predicate.
- `SOME` is a synonym for `ANY`, as documented. It was implemented as `ALL` negated, which is a different question.
- `!=` and `<>` are matched before the single-character `<` and `>`, so a two-character operator is no longer read as a comparison followed by garbage.
- `LIKE` compares literally. It inherited `MATCHES`' handling, which forced `CompareOptions::quoted` and so skipped `preg_quote()`: regex metacharacters expanded in memory while a SQL store compared them literally, and a bare `*` reached the regex engine as a quantifier with nothing to repeat. No wildcard syntax expands in `LIKE` — neither `*`/`?` nor `%`/`_` — which is what makes an exact match on a value containing a literal `%` work.
- `IN` accepts numeric collections. Its comparison closure was typed `string`, so `n IN {1,5,9}` raised a `TypeError`; only collections of strings worked.
- `BETWEEN` is inclusive at both ends and accepts a single-point range. It evaluated through `in_range()`, which is half-open by design, so it rejected its own upper bound.
- A comparison operator's options survive `predicateFormat`. `s ==[cd] "JOSE"` read back as `s = 'JOSE'` — case-sensitive to the eye while still matching `josé`, and no longer round-trippable.
- A key path resolves its collection operator through `valueForKeyPath`, so `nums.@count == 3` and `employees.@unionOfObjects.salary.@sum` evaluate inside a predicate.
- A key path carrying a second collection operator now reports what it received and why, instead of failing as an assertion or as `Call to undefined method`.
- `dateDiff` reports elapsed totals, matching SQL's `TIMESTAMPDIFF` rather than a calendar-component difference.
- `stddev` centres on the signed mean; it took the absolute value of the mean first.
- `concat` is variadic and skips nulls, like `CONCAT_WS`.
- Fifteen string functions and six bitwise functions accept the numeric types the expression engine actually produces. Their signatures required `string` or `int`, and the dispatch is dynamic, so no static analyser could see the mismatch.
- Every operator function is `static`. `dateAdd` and `dateSub` were instance methods, which the dynamic dispatch could not call.

**Key-value observing**

- A change sends one notification. `willChangeValueForKey()` notified every observer registered for the key rather than only those that asked for the pre-change notification with `KeyValueObservingOptions::prior`, so a single change arrived twice.
- `didChangeValueForKey()` reports the replaced value as `oldValue`. It reported the value that had just replaced it.
- Announcing a change on a declared but uninitialised typed property no longer raises.
- A plain assignment reports `KeyValueChange::setting`, not `replacement`.

**Networking**

- The WebSocket handshake completes. `EasyHandle::connect()` read the response through the delegate's `fill()`, whose granularity belongs to whatever the delegate needs for its own uploads: FTP support switched it from `fgets()` to `fread()`, so it began answering with a whole block. The header parser recognizes the end of a header by receiving the blank line on its own, and a block is the one shape it cannot consume, so the header never completed and the task was cancelled before opening. `connect()` now reads the socket itself and feeds the parser one line at a time, the way `CURLOPT_HEADERFUNCTION` feeds the HTTP path.
- A pathless WebSocket URL such as `wss://host` built the request line `GET  HTTP/1.1`, which is malformed. The target falls back to `/`.
- `EasyHandle::supportsWebSockets()` tested for a transport named `tpc`. The typo was latent because the `ssl` branch answered true anyway, but a build without OpenSSL would have refused WebSockets while holding a usable TCP transport.

**Other**

- `Number::compare()` stays numeric whatever the operand is spelled as. Against a string it compared the two as text, so `Number(42)` reported itself as *less* than `"9"` and a collection holding both ordered by spelling rather than by value. A class whose whole purpose is to be a number has no business answering as something else for one operand type — and it already reads `"1,234"` as a number in its own constructor. Equality is unaffected: `Number(42)` still equals `"42"`. A string that is not numeric now falls through to the parent, which answers a stable order rather than a meaningless textual one.
- `isKind()` and `isMember()` answer different questions again, as in Cocoa: the first accepts any class in the ancestry, the second only the exact class. `isMember()` delegated to `isKind()`, which collapsed the distinction that is the only reason both exist and contradicted `ObjectProtocol`, whose docblock for it deliberately omits the inheritance clause the other one carries.
- The key-value observing customization hooks are found. Both dispatched to a selector no method could match — `keyPathsForValuesAffectingValueFor<Key>` and `automaticallyNotifiesObserversFor<Key>`, where `KeyValueObserving` documents `keyPathsForValuesAffecting<Key>` and `automaticallyNotifiesObserversOf<Key>` — so a method written against the documentation was never called and the whole feature was inert.
- A dependent key is notified when one of its ingredients changes. Nothing consulted `keyPathsForValuesAffectingValueForKey()`, so declaring `full` as derived from `first` and `last` bought nothing: observing `full` reported no change when either moved. A declared dependency cycle settles instead of recursing until the stack overflows.
- `removeAll()` with a predicate no longer raises `Cannot access protected property`. The algorithm read the filtered collection's own storage, which holds only while `filter()` answers with the same class — `CollectionDifference::filter()` returns an `ArrayClass`, and protected access is per class, not per hierarchy. It reads through the public array now, which works for any collection whose `filter()` changes type.
- Property list values are escaped as text rather than parsed as markup, so a value containing `<` or `&` no longer corrupts the document or silently disappears.
- `NotificationCenter::removeObserver()` unregisters an observer wholesale, matching on the observer, the notification name and the observed object, and accepting either a registered object or an opaque block token.
- `UndoManager` tracks its grouping level, marks itself as undoing while it undoes a nested group, and treats `levelsOfUndo = 0` as unlimited. Its menu titles were crossed.
- `UserDefaults::doble()` is spelled `double()`.
- `Dictionary` keys stay strings. PHP converts a numeric-string array key to an integer internally, contradicting the declared key type on iteration and on the `keys` property.
- `IndexPath` loads. It aliased a trait's `compare()` to a private name, and PHP carries the `#[Override]` attribute onto the renamed copy, where no parent method of that name exists — a fatal error at class-load time.
- FTP uploads stream through `php://temp`, and a false-positive `fwrite()` check in `FTPURLProtocol` is corrected.
- `FileHandle` factories handle `fopen` failures, and `FileManager` guards directory contents.

### Removed

- Configuration for tools that were not run: a PHPStan config, two style configs, and a `phpcs` entry point.
- Development apparatus is excluded from the distributed package via `export-ignore`, so installing this does not put `tests/`, `psalm.xml` or the editor configuration into an application's vendor directory.
