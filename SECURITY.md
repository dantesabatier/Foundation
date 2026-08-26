# Security Policy

## Supported versions

Until 1.0 is tagged, only `master` receives security fixes. Once released, the
latest minor of the current major is supported.

| Version  | Supported |
|----------|-----------|
| `master` | yes       |

## Reporting a vulnerability

**Do not open a public issue for a security problem.** Report it privately, by
either route:

- **GitHub** — [Report a vulnerability](https://github.com/dantesabatier/Foundation/security/advisories/new)
  through the repository's private advisory form.
- **Email** — `dantesabatier@me.com`, with `SECURITY` in the subject.

Please include what you have: affected version or commit, the component
involved, the steps that reproduce it, and what an attacker gains. A proof of
concept helps, but do not delay a report to build one.

You can expect an acknowledgement within 5 days, an assessment with a planned
fix date within 14, and credit in the advisory unless you would rather stay
anonymous. Please give the fix a chance to ship before disclosing publicly; if
a report goes unanswered for 30 days, treat that as consent to disclose.

## Scope

Foundation is a base layer, so a flaw here reaches everything built on it. In
scope:

- **Predicates and expressions** — a predicate format string or key path that
  escapes its intended scope, reads a value it should not reach, or lets a
  caller-supplied argument alter the structure of the expression rather than
  its operands.
- **Key-value coding** — reaching a private or protected member through a key
  path, or invoking a method that KVC should not expose.
- **Networking** — request smuggling or header injection through `URLRequest`,
  responses trusted past what the transport verified, and TLS verification
  that can be silently disabled.
- **Serialization** — object instantiation or method invocation driven by
  untrusted archived data.
- **Filesystem and bundles** — path traversal through `FileManager` or bundle
  resource lookup escaping the bundle.
- **Localization** — a format string reaching a formatter from untrusted input.

Out of scope: vulnerabilities in code that merely uses Foundation rather than
in Foundation itself, findings that require an already-compromised server or a
modified deployment, denial of service through sheer input volume, and reports
produced solely by a scanner with no demonstrated impact.

The sibling libraries [CoreData](https://github.com/dantesabatier/CoreData) and
[Service](https://github.com/dantesabatier/Service) have their own
repositories; report an issue in either against the one it belongs to, or here
if you are unsure which.
