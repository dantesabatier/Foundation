# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sabatier Foundation is a PHP port of Apple's Foundation framework. It provides Swift-inspired collection types, Key-Value Coding/Observing, an NSPredicate-like filtering system, file management, and a full HTTP/WebSocket networking stack. It has no external runtime dependencies and targets PHP ^8.5.

## Commands

Development tools (Psalm, PHPStan, PHP-CS-Fixer, PHP_CodeSniffer, Rector) are installed **globally** via Composer (`%APPDATA%\Composer\vendor\bin`), not in the project's `vendor/` directory — `vendor/` only holds the autoloader. Do not run `composer install` to get them; invoke the global binaries directly:

```bash
# Static analysis (configured via psalm.xml)
psalm

# Code style check
phpcs

# Code style fix
php-cs-fixer fix

# PHPStan analysis
phpstan analyse

# Rector dry-run
rector process --dry-run
```

There is no test suite — correctness is enforced entirely through static analysis (Psalm level 4, PHPStan level 3).

## Architecture

### Namespace & Autoloading

All classes live under `Sabatier\Foundation\` mapping to `src/`. Several global helper files are autoloaded via `files` in `composer.json` (constants, internal helpers, namespace-extension functions).

### Collection Hierarchy

Protocol/interface stack mirrors Swift's stdlib: `Sequence → Collection → BidirectionalCollection → MutableCollection → RangeReplaceableCollection`. Concrete types:

- `ArrayClass<Element>` — ordered, random-access, implements the full stack
- `Dictionary<Key, Element>` — key-value store
- `Set<Element>` — unordered unique elements with `SetAlgebraAlgorithms` (union, intersection, etc.)

Algorithms are in **traits**, not in the classes themselves (e.g., `RangeReplaceableCollectionAlgorithms`, `SequenceAlgorithms`). When multiple traits define the same method name, the class aliases one to resolve the conflict:

```php
use RangeReplaceableCollectionAlgorithms {
    filter as private sequenceFilter;
}
```

### ObjectClass & Protocols

`ObjectClass` is the root class. It implements `ObjectProtocol` (runtime type introspection: `isKind`, `isMember`, `responds`), `KeyValueCoding`, `KeyValueObserving`, `Comparable`, and `JsonSerializable`. Most domain classes extend it.

### Key-Value Coding / Observing

`KeyValueCodingInternal.php` implements KVC collection operators (`@sum`, `@avg`, `@count`, `@max`, `@min`, `@median`, `@distinctUnionOfObjects`, etc.). Key paths like `"department.manager.salary"` and `"@sum.attributes.size"` are resolved at runtime.

### Predicates (`src/Predicates/`)

~47 classes modeling NSPredicate. `Predicate` is the abstract base with factory methods (`Predicate::format()`). `PredicateScanner` parses predicate format strings; `PredicateVisitor` evaluates them against objects. Key subtypes: `ComparisonPredicate`, `CompoundPredicate` (AND/OR/NOT), and expression types (`KeyPathExpression`, `FunctionExpression`, `VariableExpression`).

### Networking (`src/Networking/`)

~79 files providing a URLSession-style HTTP/FTP/WebSocket client. `EasyHandle` wraps CURL. `URLSession` is the top-level API with data, download, upload, and WebSocket tasks. Authentication is handled via `URLCredential` / `URLAuthenticationChallenge`. Responses are cacheable via `URLCache`.

### Property Hooks (PHP 8.4+)

Computed properties use PHP 8.4 property hooks extensively instead of explicit getters:

```php
public string $absoluteString {
    get {
        return $this->baseURL === null ? $this->string : $this->absoluteURL->absoluteString;
    }
}
```

Asymmetric visibility (`private(set)`) is also used.

### Generics

Collections are annotated with PHPDoc `@template` tags for Psalm/PHPStan inference. A custom Psalm plugin (`src/Plugins/Psalm/`) extends return-type inference for `compactMap()`.

### Static Analysis Suppressions

`psalm.xml` suppresses ~30 specific issue types for particular files (mostly in networking code where CURL/HTTP complexity defeats inference). Before adding new suppressions, check whether the actual code can be made type-safe instead.
