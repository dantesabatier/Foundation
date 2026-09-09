# Conventions

A checklist of the mechanical conventions this codebase follows, with the search that finds each violation. [CLAUDE.md](CLAUDE.md) describes the architecture and the reasoning behind the design; this file is the narrower thing: what to check before opening a pull request, and how.

Most entries carry a reason. Where the convention is a preference rather than a rule, that is said explicitly — a few of these have legitimate exceptions, and applying them blindly makes the code worse.

## Language and file layout

**Every file declares strict types.** `declare(strict_types=1);` goes after `<?php` and any file-level docblock, before `namespace`.

```bash
grep -rL "declare(strict_types=1)" src/ tests/
```

**Comments, docblocks and identifiers are in English.** The convention follows the code, not the language of whatever conversation produced the change.

**String literals use double quotes.** Single quotes appear only where the string contains a double quote worth not escaping.

**Public constants are `PascalCase`**, not `UPPER_SNAKE_CASE` — `MimeTypeJPEG`, `NotFound`, `URLErrorTimedOut`. This departs from the usual PHP idiom, so it is the one most often "corrected" by mistake. The internal build-time switches in `src/ConstantsInternal.php` (`USE_UNSAFE_FUNCTIONS`, `USER_DEFAULTS_SIZE_LIMIT`) keep `UPPER_SNAKE_CASE` deliberately: the casing is what marks them as not being API.

```bash
rg -n "\bconst\s+(string|int|float|bool|array)?\s*[A-Z_]+[A-Z_0-9]\s*=" src/
```

**Classes are imported, never written inline.** `new stdClass()` with `use stdClass;` at the top, not `new \stdClass()` — even for global-namespace classes, even once. `use function` and `use const` statements are where a qualified path belongs.

```bash
rg -n '(new|instanceof|catch \(|assertInstanceOf\(|extends|implements)\s+\\[a-zA-Z]' src/ tests/
```

Verify that search returns a known case before trusting a clean result: a shell can mangle the backslash and silently match nothing.

## Classes and properties

**Concrete classes are `final`** unless something in `src/` actually extends them. Before flagging one, check:

```bash
rg -n "extends Foo\b" src/
```

An abstract class never gets `final`, and a deliberate base with subclasses is not a violation.

**Properties come before methods.** Every property — plain, hooked, `static`, `readonly` — belongs above the first non-constructor method. Promoted constructor parameters do not close the section; the first ordinary method does. Adding a hooked property next to the method that reads it is the usual way this breaks.

**Property docblocks are a single line:** `/** @var Type Description */`. Multi-line is for the rare property needing several annotations that will not fit readably.

**Computed properties use hooks, not getters.** A parameterless method that only derives a value from `$this` should be a property with a `get` hook. When the value should be computed once, cache it into the backing store:

```php
private ArrayClass $loadedClasses {
    get => $this->loadedClasses ??= new ArrayClass();
}
```

A **private** hook that recomputes on every read is declaring storage it never uses — either cache it (`??=`, or an `isset` guard plus `return $this->x = ...` for a multi-statement body) or make it a method. A **public** computed property is the opposite: it must reflect current state on every read, so caching one makes it stale. Never cache a hook that validates a live relationship.

## Collections

Prefer `ArrayClass`, `Dictionary` and `Set` over native `array` on the declared surface — properties, parameters, return types. They carry `->count`, `->first`, `map`/`filter`/`reduce`/`compactMap`/`grouping`/`sum`, and `Dictionary` answers `null` for a missing key instead of raising a notice.

This is a preference, not a prohibition. A native array stays where it is genuinely required: a signature fixed by an interface (`jsonSerialize(): array`), a value handed straight to a native function, a short-lived local, or a typed shape (`array{a: string, b: Foo}`) read only by its known keys — Psalm checks those field by field, and a `Dictionary` would verify less.

Once a value *is* a collection:

- `$collection->append($x)`, never `$collection[] = $x`
- `$collection->count`, never `count($collection)`
- `$dictionary["k"]`, never `$dictionary["k"] ?? null` — `Dictionary::offsetGet` already answers `null`, so the fallback is a no-op. This applies to `Dictionary` only: an `ArrayClass`/`Set` index carries no such guarantee and removing the fallback there changes behaviour.
- A collection index assigned with a real fallback (`?? 0`, `?? new X()`, `?? throw`) earns a type-only `/** @var Type $x */` above it. Psalm does not narrow the generic after `??`, and this is a standing false positive rather than a code problem.

**Prefer the functional algorithms over hand-rolled loops** where the loop only accumulates a result, finds one element, or sets a flag. A loop with side effects, or one whose body will not read cleanly as a closure, stays a loop.

**Do not reinvent the helpers in `src/StandardAdditions.php`.** `string_split_trimmed`, `array_remove`, `is_equal`, `compare`, `is_sequential`, `base64_url_encode`, `is_ascii`, `canonical`, `in_range` each replace a raw idiom. The helpers are namespaced, so a caller outside `Sabatier\Foundation` needs `use function`. A class's own `isEqual()`/`compare()` body is not a violation — those are the implementations the helpers dispatch to.

## Comments

**A comment earns its place by saying what the code cannot.** The *why*: a constraint imposed from outside, a bug being avoided, an alternative deliberately rejected, the reason an oblique construct exists. A comment that restates the line below it is noise.

```php
// Wrong — the line already says this
// increment the counter
$count++;

// Right — records something invisible
// Reads through the public array rather than the filtered collection's own storage: filter() may answer with a different class, and protected access is per class, not per hierarchy.
$this->reserved = $this->filter(...)->array;
```

**Comment prose goes on one line**, however long. The editor soft-wraps it to the window; a comment pre-broken at some column fights that and reflows the whole block in a diff when a sentence changes. Genuinely itemised comments — a list, one trailing note per line of code — are structure, not wrapped prose, and stay as they are.

```bash
rg -Un "^\s*//[^\n]*\n\s*//" src/ tests/
```

**Public API is documented; internal plumbing is not.** A public class, property or method carries a docblock, because that is what a reader sees when hovering the symbol at a call site. A member marked `@internal`, or `private`/`protected`, carries only annotations that the signature cannot express (`@param Set<Permission>`, `@throws`, `@psalm-*`) — descriptive prose there is the violation, and a private method whose name does not convey its intention is a naming problem, not a documentation one.

A method's `@param` list is all-or-nothing: declaring one obliges declaring every parameter, in signature order. A parameter needing no description is listed with type and name alone. Any parameter, return or property whose type is a generic collection **must** name its type argument — a signature saying `Set $permissions` does not say `Set<Permission>`, and neither the reader nor the analyzer can recover it.

**A constructor with parameters carries a `@param` docblock**, promoted properties included. A constructor with none needs no docblock. A subclass constructor that only re-forwards its parent's parameters unchanged should be deleted so the parent's is inherited.

## Tests

Test files follow every convention above. Beyond that:

- Each suite's header docblock lists the regressions it guards, in prose — what the defect was and why the fix is right. That docblock is what stops a future reader from undoing the work.
- A fixture class in a test file is `final` unless a test subclasses it.
- Pin behaviour you deliberately chose not to change, with a comment saying so, rather than leaving it untested. A test that fails when someone implements the missing piece is a feature.

## Checking before a pull request

The static analysers catch a different set of problems than this list does, and the IDE inspections catch a third. Run all three:

```bash
phpunit
psalm
rector process --dry-run
```

`psalm.xml` analyses `src/` only. Running Psalm manually over `tests/` produces a large number of false positives — it infers literal types for test data — so a clean `psalm` run is the bar, not a clean run over an individual test file.

IDE inspections are evidence, not verdicts. A good share are false positives: a mixin's "unresolved variable", a "pipe operator" suggestion on a multi-argument call, an "unused" property that a reference writes through. Judge each one against the code before acting on it, and be wary of the inverse — a fix that exists only to quiet a warning can change behaviour that no inspection was watching.
