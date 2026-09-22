# Versioning

The Sabatier stack — [Foundation](https://github.com/dantesabatier/Foundation),
[CoreData](https://github.com/dantesabatier/CoreData) and
[Service](https://github.com/dantesabatier/Service) — follows
[Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html). This document
says what that means here, because the general rule only becomes useful once a
project states what counts as its public surface.

It applies to all three packages. They are versioned independently: a release of
one does not oblige a release of the others.

## What is covered

The public API is everything an application can reach that is not marked
otherwise:

- Public and protected classes, interfaces, traits, enums and functions.
- Their public and protected methods and properties, including property hooks.
- Global constants and the functions declared in each package's `files`
  autoload entry.
- The names and semantics of environment variables the framework reads.
- The predicate format-string grammar, documented in
  [PREDICATES.md](PREDICATES.md).

**Not covered**, and changeable in any release:

- Anything annotated `@internal`. The three packages use it deliberately and
  extensively; treat it as the boundary it is.
- Anything `private`, and any protected member of a class annotated
  `@internal`.
- The wording of exception messages and log lines. The *type* thrown is part of
  the API; the sentence inside it is not.
- The shape of the test suite, the development tooling, and everything
  `export-ignore` keeps out of the distributed package.

## What a major release is for

A change is breaking when code that used the documented API correctly stops
compiling, stops running, or starts behaving differently. In this stack, that
includes some cases worth naming explicitly, because they are easy to make
without noticing:

- Removing or renaming any covered symbol, or narrowing its visibility.
- Adding a required parameter, narrowing a parameter type, or widening a return
  type.
- Adding an abstract method to a class an application is expected to subclass —
  `ManagedObject`, `Responder`, `ViewController`, `ObjectClass`.
- Changing which exception type a documented failure throws, or moving where it
  is thrown. Exception types are control flow here: `Application`'s pipeline
  turns them into an HTTP status, its headers and its body, and the LLM runtime
  reads `isTransient` to decide whether a failure is worth retrying.
- Changing the persistent store format, or a model version hash, in a way an
  existing store cannot migrate through.
- Changing a default that silently alters behaviour — a rate limit, a token
  lifetime, a cache policy, a delete rule.
- Changing the SQL a given model generates in a way that alters an existing
  schema.

A minor release adds; a patch release fixes without adding. Both must leave a
working application working after `composer update`.

## Why this matters more than it looks

Every project built on this stack declares `^1.0`, which means every `1.x`
released reaches it on the next `composer update`, without anyone reviewing the
change. Publication turned each of these packages from a directory that could be
edited freely into a dependency other people's deployments resolve. A break
shipped in a minor version is a break in somebody's production.

## Keeping the changelog

A change visible to someone using the package is written into `CHANGELOG.md`
under `## [Unreleased]` as part of making it, not gathered afterwards. The
commit that changes behaviour is the only moment when what changed, and why,
are both still known; reconstructing it from a range of commits at tag time
produces entries nobody can verify.

Visible means a consumer could notice: behaviour, a signature, a default, a
message they read, the version a bundle reports. Test scaffolding, CI, analysis
configuration and internal documentation are not, and a changelog that records
them buries the entries that matter.

Tagging then renames `## [Unreleased]` to `## [<version>] - <YYYY-MM-DD>` and
opens an empty `## [Unreleased]` above it. That is an edit, not an act of
recall.

## Before a release

The checks below are what [`Tools/verify-release.sh`](Tools/verify-release.sh)
automates; the script is the executable form of this section, not a replacement
for reading it.

1. The suite passes, and CI is green on `master`.
2. Psalm reports no errors.
3. A clean checkout installs from Packagist and runs — not the working copy,
   and not a path repository. The two are not the same thing, and the
   difference is exactly what a consumer hits first.
4. `CHANGELOG.md` has a section for the version, dated, under the headings
   [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) uses, and its
   entries were written as the changes were made rather than assembled now.
5. The tag matches the changelog entry, and is annotated.
6. `Info.plist` carries the same version as the tag. `CFBundleShortVersionString`
   is the released version — `1.0.1`, never `v1.0.1` — and `CFBundleVersion` is
   the build number, incremented once per release. Nothing derives one from the
   other, so a release that skips this ships a bundle claiming a version it is
   not; both packages that predate this rule had drifted, one of them by a whole
   major. `composer.json` deliberately declares no `version`: Packagist reads
   the tag, and a third copy is a third thing to forget.

## Working against a local checkout

A path repository is for iterating on the stack itself, never for the manifest
that ships. Composer offers a directory as `dev-master` unless it is told
otherwise, which `^1.0` then refuses, so a local checkout needs an explicit
version:

```bash
composer config repositories.foundation --json '{"type":"path","url":"../Foundation","options":{"versions":{"sabatier/foundation":"1.0.0"}}}'
```

Revert it before committing. A manifest that names a sibling directory resolves
on one machine and nowhere else.
