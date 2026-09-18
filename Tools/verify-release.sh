#!/usr/bin/env sh
#
# Verifies that what Packagist serves for a package actually installs and runs.
#
# The working copy is not the package. A path repository resolves symlinks into
# a checkout, `export-ignore` removes files from the distributed archive, and a
# dependency that only exists in a global Composer install is invisible until
# someone else tries. This script installs the published package into an empty
# directory the way a consumer would, and reports what they would get.
#
# Usage:
#   Tools/verify-release.sh                       # the package in the current directory
#   Tools/verify-release.sh sabatier/service      # a specific package
#   Tools/verify-release.sh sabatier/coredata ^1.0
#
# Exits non-zero on the first check that fails.

set -eu

package="${1:-}"
constraint="${2:-^1.0}"

if [ -z "$package" ]; then
    if [ ! -f composer.json ]; then
        echo "No composer.json here, and no package named. See the usage note above." >&2
        exit 2
    fi
    package=$(php -r 'echo json_decode(file_get_contents("composer.json"), true)["name"] ?? "";')
    if [ -z "$package" ]; then
        echo "composer.json declares no name." >&2
        exit 2
    fi
fi

say() { printf '\n== %s\n' "$1"; }
fail() { printf '   FAILED: %s\n' "$1" >&2; exit 1; }

say "Verifying $package $constraint"

# ---------------------------------------------------------------------------
# 1. Packagist serves the constraint at all.
# ---------------------------------------------------------------------------
say "Published versions"
metadata=$(curl -fsS "https://repo.packagist.org/p2/$package.json" 2>/dev/null) \
    || fail "Packagist serves no metadata for $package. Check the name, or register the package."

versions=$(printf '%s' "$metadata" | php -r '
    $d = json_decode(stream_get_contents(STDIN), true) ?: [];
    foreach ($d["packages"] ?? [] as $versions) {
        foreach ($versions as $v) { echo $v["version"], "\n"; }
    }
')

[ -n "$versions" ] || fail "$package is registered but has no published version. A tag is missing, or Packagist has not crawled it yet."
echo "$versions" | sed 's/^/   /'

# ---------------------------------------------------------------------------
# 2. It installs into an empty project, from the archive Packagist serves.
# ---------------------------------------------------------------------------
say "Clean install"
# A short path: Windows checkouts of vendored git metadata hit MAX_PATH otherwise.
directory=$(mktemp -d 2>/dev/null || mktemp -d -t sabatier-verify)
trap 'rm -rf "$directory"' EXIT INT TERM

cat > "$directory/composer.json" <<JSON
{
    "name": "sabatier/release-verification",
    "description": "Throwaway consumer used to verify a published package.",
    "require": { "$package": "$constraint" }
}
JSON

( cd "$directory" && composer update --no-dev --prefer-dist --no-interaction --no-progress ) \
    || fail "$package $constraint does not install from Packagist"

installed=$(cd "$directory" && composer show "$package" --format=json | php -r '
    $d = json_decode(stream_get_contents(STDIN), true) ?: [];
    echo $d["versions"][0] ?? "?";
')
echo "   installed $installed"

# ---------------------------------------------------------------------------
# 3. The autoloader resolves the package's own classes.
# ---------------------------------------------------------------------------
say "Autoloading"
( cd "$directory" && php -r '
    require "vendor/autoload.php";
    $composer = json_decode(file_get_contents("vendor/" . $argv[1] . "/composer.json"), true);
    $namespaces = array_keys($composer["autoload"]["psr-4"] ?? []);
    if ($namespaces === []) { fwrite(STDERR, "   no psr-4 autoload declared\n"); exit(1); }
    $checked = 0;
    foreach ($namespaces as $namespace) {
        $directory = "vendor/" . $argv[1] . "/" . $composer["autoload"]["psr-4"][$namespace];
        if (!is_dir($directory)) { fwrite(STDERR, "   $namespace maps to a missing directory: $directory\n"); exit(1); }
        foreach (new DirectoryIterator($directory) as $entry) {
            if ($entry->isDot() || $entry->getExtension() !== "php") { continue; }
            $class = $namespace . $entry->getBasename(".php");
            if (!class_exists($class) && !interface_exists($class) && !trait_exists($class) && !enum_exists($class)) { continue; }
            $checked++;
            if ($checked >= 5) { break 2; }
        }
    }
    if ($checked === 0) { fwrite(STDERR, "   the autoloader resolved nothing\n"); exit(1); }
    printf("   resolved %d classes through the published autoloader\n", $checked);
' "$package" ) || fail "the published package does not autoload"

# ---------------------------------------------------------------------------
# 4. Development apparatus stayed out of the archive.
# ---------------------------------------------------------------------------
say "Distributed contents"
for unwanted in psalm.xml rector.php phpunit.xml CLAUDE.md AGENTS.md .github; do
    if [ -e "$directory/vendor/$package/$unwanted" ]; then
        echo "   note: $unwanted ships with the package; add it to .gitattributes if that is unintended"
    fi
done
echo "   checked"

say "OK — $package $installed installs and runs as published"
