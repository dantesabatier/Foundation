# AGENTS.md

## Commands

Tools are installed globally and available in PATH.

```bash
composer psalm                    # Psalm level 4 (config: psalm.xml)
phpstan analyse                   # PHPStan level 3 (config: phpstan.neon)
php-cs-fixer fix                  # Code style (PSR12, config: .php-cs-fixer.dist.php)
rector process --dry-run          # Code quality check (config: rector.php)
```

No test suite exists — correctness is enforced entirely through static analysis.

## PHP Requirements

- **PHP ^8.5** with extensions: gd, mbstring, curl, intl, gettext, dom, ctype, simplexml
- Platform config fakes `ext-pcntl` and `ext-posix` (not actually required at runtime)

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
- PHPStan suppressions in `phpstan.neon`
- Before adding new suppressions, try making code type-safe first
- Custom Psalm plugin registered via `composer.json` extra: `Sabatier\Foundation\Plugins\Psalm\Plugin`

## Autoloading

Global helper files autoloaded via `files` in `composer.json`: `Constants.php`, `ConstantsInternal.php`, `KeyValueCodingInternal.php`, `StandardAdditions.php`, `URLAdditions.php`, `UUIDAdditions.php`, and others in `src/` and `src/Networking/`.
