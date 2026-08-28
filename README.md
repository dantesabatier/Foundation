# Foundation for PHP

A faithful, high-level port of Apple's **Foundation** framework to PHP.

This project brings the robust, object-oriented architecture and expressive APIs of Swift/Objective-C Foundation to the PHP ecosystem, leveraging modern features like Property Hooks (PHP 8.4+), Enums, and advanced Static Analysis.

## 🚀 Key Features

- **Swift-like Collections**: Enhanced `ArrayClass`, `Dictionary`, and `Set` with support for `map`, `filter`, `reduce`, `compactMap`, and more.
- **Key-Value Coding (KVC) & Observing (KVO)**: Full implementation of dynamic property access and observation, including collection operators like `@sum`, `@avg`, and `@distinctUnionOfObjects`.
- **File Management**: A powerful `FileManager` API that mirrors `NSFileManager`, including URL-based paths and delegate support.
- **Advanced Predicates**: Complex data filtering and evaluation using a port of `NSPredicate`. The grammar is documented in [PREDICATES.md](PREDICATES.md).
- **Strongly Typed**: Built from the ground up for **PHPStan** and **Psalm**, ensuring type safety even in complex collection hierarchies.
- **Modern PHP**: Utilizes PHP 8.4 features such as *Property Hooks* and *Asymmetric Visibility* for a cleaner, more declarative syntax.

## 📦 Installation

```bash
composer require sabatier/foundation
```
## 🛠 Usage Example
### Collections & Functional Algorithms

```php
use Sabatier\Foundation\ArrayClass;

$array = new ArrayClass([1, 2, 3, 4, 5]);
$evenSquares = $array
    ->filter(fn($n) => $n % 2 === 0)
    ->map(fn($n) => $n * $n);

echo $evenSquares->join(", "); // "4, 16"
```

### Key-Value Coding Operators

```php
$totalSize = $files->valueForKeyPath("@sum.attributes.size");
```
### File Manager

```php
$fm = FileManager::default();
$documents = $fm->url(SearchPathDirectory::documentsDirectory, SearchPathDomainMask::user);
$configUrl = $documents->appendingPathComponent("config.plist");

if (!$fm->fileExists($configUrl->path)) {
    // ...
}
```

## License

This project is licensed under the MIT License. See the `LICENSE.md` file for details.
