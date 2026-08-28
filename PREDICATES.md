# Predicate Syntax

The predicate grammar Sabatier Foundation understands. A predicate describes *what* you
want, not how to obtain it: you write the condition once as a string, build a predicate
from it, and use it either over a collection already in memory or through a fetch.

```php
$predicate = Predicate::format("name BEGINSWITH[cd] %s AND ANY orders.total > %f", new ArrayClass(["jo", 100.0]));

$matches = $sequence->filtered($predicate);   // in memory
$request->predicate = $predicate;             // or through a store
```

What a store does with it is the store's concern. One backed by an in-memory collection
runs the same `filtered(…)`; a relational store compiles the predicate to SQL and lets
the database run it. The condition never changes.

## Comparison operators

| Operator | Meaning |
|---|---|
| `==`, `=` | equal |
| `!=`, `<>` | not equal |
| `<`, `>` | less than, greater than |
| `<=`, `=<` | less than or equal |
| `>=`, `=>` | greater than or equal |
| `IN` | membership in a collection |
| `BETWEEN { lower, upper }` | inclusive range, both ends |

## Compound predicates

`AND` (or `&&`), `OR` (or `||`), `NOT` (or `!`). **`AND` binds tighter than `OR`**, so
`a AND b OR c` is `(a AND b) OR c`; group with parentheses to override it.
`TRUEPREDICATE` and `FALSEPREDICATE` always hold and never hold.

## String operators

- `CONTAINS`, `BEGINSWITH`, `ENDSWITH` — substring, prefix, suffix.
- `MATCHES` — regular expression, against the **whole** string, so `l+` does not match
  `"hello"` but `.*l+.*` does.
- `LIKE` — compares the whole string **literally**. No wildcard expands, in either
  syntax: `*` and `?` match as themselves, and so do SQL's `%` and `_`.

### Case and diacritic modifiers

String comparisons are case- and diacritic-sensitive by default. Append a modifier
immediately after the operator:

| Modifier | Effect |
|---|---|
| `[c]` | case-insensitive |
| `[cd]` | also diacritic-insensitive |
| `[cdn]` | also normalized |

Each letter implies the ones before it, so `[d]` and `[n]` are not valid on their own.
The locale-sensitive `l` is **not supported**: a predicate carrying it raises rather than
comparing.

### Choosing a string operator

Since `LIKE` expands no wildcard, use `BEGINSWITH` / `CONTAINS` / `ENDSWITH` for a
prefix, substring or suffix search, and `MATCHES` when a real pattern is needed. What
`LIKE` buys over `==` is that a value holding a literal `%` or `_` — common in SKUs,
folios and part numbers — is found as written, in memory and through a SQL store alike.

## Aggregate operators

- `ANY` (or `SOME`) — at least one element of a to-many key path matches.
- `ALL` — every element matches.
- `NONE` — no element matches.
- `SUBQUERY(collection, $item, predicate)` — filter a collection, then chain, e.g.
  `SUBQUERY(items, $x, $x.price > 10).@count > 0`.

## Expressions

- **Key paths** — dotted, e.g. `address.city`, and collection operators such as
  `@count`, `@sum`, `@avg`, `@max`, `@min`, `@median`, `@mode`, `@stddev`,
  `@unionOfObjects`, `@distinctUnionOfObjects`.
- **Arithmetic** — `+`, `-`, `*`, `/`, `%` (modulo), `**` (power). Multiplication binds
  tighter than addition.
- **Collection indexing** — `[FIRST]`, `[LAST]`, `[SIZE]`, or `[n]` for a specific index.
- **Set expressions** — `UNION`, `INTERSECT`, `MINUS`.
- **`TERNARY(condition, ifTrue, ifFalse)`**.
- **`FUNCTION(operand, "selector", arguments…)`** for a built-in or an object's own
  method.

Bitwise operations are available in selector form — `bitwiseAnd:with:`,
`bitwiseOr:with:`, `bitwiseXor:with:`, `leftshift:by:`, `rightshift:by:` — not as infix
symbols. Their operands are rounded to integers.

## Values

- **Literals** — numbers, quoted strings (`"…"` or `'…'`), `TRUE`/`YES`, `FALSE`/`NO`,
  `NULL`/`NIL`.
- **`SELF`** — the object being evaluated.
- **Substitution variables** — `$NAME`, resolved at evaluation time through
  `withSubstitutionVariables(…)`. Use these in saved fetch request templates.
- **Format placeholders** — `%K` (key path), `%@` (object value), and the printf-style
  `%d`, `%f`, `%s`. These are filled positionally from the arguments passed when the
  predicate is built, so a template parsed without arguments must use `$VARIABLE`
  instead.

## Tracing an evaluation

Setting `Predicate::$debugDefault = true` logs every step of an evaluation — which
accessor each key path segment went through, what it produced, and how the operator
compared the two sides:

```
keyPath: Dictionary::valueForKey(employees)  => [<Employee 5>, <Employee 6>]
keyPath: Set::valueForKey(salary)            => [100, 200]
keyPath: Set::valueForKeyPath(@sum)          => 300
equalTo (direct): (Number)300 = (float)300   => true
```

It is the quickest way to see why a predicate answers what it answers, and where one
stops when it raises.

## Known limits

- A `(` at the start of a comparison always opens a predicate group, never a
  parenthesised expression, so `(n) == 2` and `(n + 1) * 2 == 6` do not parse. The same
  parentheses inside an expression do: `2 * (n + 1) == 6`.
- A reducing operator cannot follow a flattening one:
  `departments.employees.@unionOfObjects.salary.@sum` raises. Flatten to the values and
  reduce separately.
- A collection operator needs a Foundation collection — `ArrayClass`, `Set` or
  `Dictionary`. A native PHP array cannot answer one.
- `@count` counts elements of any kind, but `@sum` and `@avg` reduce them, so over a
  collection of objects the key path has to reach the number itself. **Inside a predicate
  the operator has to come last** — `employees.salary.@sum` works,
  `employees.@sum.salary` raises, even though `valueForKeyPath(…)` accepts both orders.
  `employees.@sum` alone has nothing to add up and raises either way.
- `predicateFormat` does not re-emit the comparison modifiers, so a predicate built from
  `name ==[cd] "JOSE"` prints as `name = 'JOSE'`. The comparison itself still honours
  them; only the round-trip through the format string loses them.

## Examples

```
name == $NAME AND status == 1
email CONTAINS[cd] $QUERY
date >= $START AND date < $END
amount BETWEEN { $MIN, $MAX }
ANY tags.name IN { $TAG1, $TAG2 }
employees.@count > 0
employees.salary.@sum > 10000
SUBQUERY(items, $x, $x.price > $MIN).@count > 0
age < TERNARY(name MATCHES[c] "jane", 30, 40)
```
