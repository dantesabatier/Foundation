# Foundation for PHP

A faithful, high-level port of Apple's **Foundation** framework to PHP.

This project brings the robust, object-oriented architecture and expressive APIs of Swift/Objective-C Foundation to the PHP ecosystem, leveraging modern features like Property Hooks (PHP 8.4+), Enums, and advanced Static Analysis.

## 🚀 Key Features

- **Swift-like Collections**: Enhanced `ArrayClass`, `Dictionary`, and `Set` with support for `map`, `filter`, `reduce`, `compactMap`, and more.
- **Key-Value Coding (KVC) & Observing (KVO)**: Full implementation of dynamic property access and observation, including collection operators like `@sum`, `@avg`, and `@distinctUnionOfObjects`.
- **File Management**: A powerful `FileManager` API that mirrors `NSFileManager`, including URL-based paths and delegate support.
- **Advanced Predicates**: Complex data filtering and evaluation using a port of `NSPredicate`.
- **Strongly Typed**: Built from the ground up for **PHPStan** and **Psalm**, ensuring type safety even in complex collection hierarchies.
- **Modern PHP**: Utilizes PHP 8.4 features such as *Property Hooks* and *Asymmetric Visibility* for a cleaner, more declarative syntax.

## 📦 Installation

```bash
composer require sabatier/foundation
```
