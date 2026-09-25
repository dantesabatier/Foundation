# AGENTS.md

## Commands

Tools come from `require-dev`: always run the project's own `vendor/bin`, never the global Composer binaries, which do not load this project's autoload.

```bash
php vendor/bin/psalm --show-info=true --force-jit  # Psalm level 4 (config: psalm.xml)
php vendor/bin/rector process --dry-run            # Code quality check (config: rector.php)
php vendor/bin/phpunit                             # Test suite (all, or a single file)
```

There is no formatter — match the surrounding code (PSR-12, double-quoted strings, short array syntax).

## PHP Requirements

- **PHP ^8.5** with extensions: mbstring, curl, intl, gettext, dom, ctype
- Suggested, not required: gd (only `Bundle::image()`) and simplexml (only the bundled Psalm plugin). Property lists use ext-dom, not simplexml.
- Platform config fakes `ext-posix`, which Windows does not have. It is used — user lookups, `posix_strerror()`, TTY detection — but every call sits behind `function_exists()`, so it is optional at runtime and stays out of `require`.

## Architecture

Single-package repo, all code under `src/`:

- `src/` — core classes (ArrayClass, Dictionary, Set, ObjectClass, etc.)
- `src/Networking/` — URLSession-style HTTP/WebSocket client (~79 files)
- `src/Predicates/` — NSPredicate port (~47 classes)
- `src/Plugins/Psalm/` — custom Psalm plugin for `compactMap()` return types

## Key Patterns

- **Collection hierarchy**: `Sequence → Collection → BidirectionalCollection → MutableCollection → RangeReplaceableCollection`
- **Algorithms in traits**: e.g., `SequenceAlgorithms`, `RangeReplaceableCollectionAlgorithms`. When traits conflict, class aliases resolve: `filter as private sequenceFilter`
- **Property hooks (PHP 8.4+)**: Computed properties use hooks instead of getters. Asymmetric visibility (`private(set)`) used throughout
- **Generics**: PHPDoc `@template` annotations for Psalm/PHPStan; custom Psalm plugin extends inference

## Static Analysis

- Psalm suppressions (~30) in `psalm.xml` — mostly networking code where CURL complexity defeats inference
- Before adding new suppressions, try making code type-safe first
- Custom Psalm plugin registered via `composer.json` extra: `Sabatier\Foundation\Plugins\Psalm\Plugin`


## Autoloading

Global helper files autoloaded via `files` in `composer.json`: `Constants.php`, `ConstantsInternal.php`, `KeyValueCodingInternal.php`, `StandardAdditions.php`, `URLAdditions.php`, `UUIDAdditions.php`, and others in `src/` and `src/Networking/`.

## Releasing

`Info.plist` carries the released version, and nothing derives it from the git tag. When a release is cut, `CFBundleShortVersionString` becomes the tagged version (`1.0.1`, never `v1.0.1`) and `CFBundleVersion` — the build number — is incremented. `composer.json` declares no `version`: Packagist reads the tag.

`CHANGELOG.md` is written as the change is made, under `## [Unreleased]`, when a consumer would notice it — behaviour, a signature, a default, a message they read. Tagging renames that section and opens an empty one; it does not gather entries.

The full policy, and what else runs before a tag, is in [VERSIONING.md](VERSIONING.md).
