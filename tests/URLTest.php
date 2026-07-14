<?php

declare(strict_types=1);

/**
 * Standalone tests for src/URL.php.
 *
 * Run with: php tests/URLTest.php
 * Exits with a non-zero status code if any check fails.
 *
 * Regression guards, in the order the bugs were found:
 *  - component getters must parse the raw string and decode afterwards, so an encoded "#"
 *    or "?" inside a component can no longer truncate the URL at the wrong place;
 *  - deleteLastPathComponent / deletePathExtension must operate on the path only, never on
 *    the host, query, or fragment, and must leave a URL with no path components unchanged;
 *  - relative references must resolve against baseURL per RFC 3986 section 5;
 *  - mutators leave the URL absolute (no double resolution against a stale baseURL);
 *  - filesystem-backed calls (hasDirectoryPath, fileSystemRepresentation,
 *    resolveSymlinksInPath) must cope with Windows drive paths and missing files without
 *    destroying the URL.
 */

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\URL;

require __DIR__ . "/../vendor/autoload.php";

final class URLTestRunner
{
    public static int $passed = 0;
    /** @var list<string> */
    public static array $failures = [];
    private static string $section = "";

    public static function section(string $name): void
    {
        self::$section = $name;
    }

    public static function check(bool $condition, string $message): void
    {
        if ($condition) {
            self::$passed++;
            return;
        }
        $failure = self::$section === "" ? $message : self::$section . ": " . $message;
        self::$failures[] = $failure;
        fwrite(STDERR, "FAIL $failure" . PHP_EOL);
    }

    public static function finish(): never
    {
        $failed = count(self::$failures);
        printf("%d passed, %d failed%s", self::$passed, $failed, PHP_EOL);
        exit($failed > 0 ? 1 : 0);
    }
}

/** Fails the process on any PHP warning/notice, so a resurfaced readlink()/realpath() warning is caught. */
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$check = URLTestRunner::check(...);
$section = URLTestRunner::section(...);

// ---------------------------------------------------------------------------
$section("component parsing");
// ---------------------------------------------------------------------------

$url = new URL("https://user:p%40ss@example.com:8443/a/b.txt?x=1&y=2#frag");
$check($url->scheme === "https", "scheme parsed");
$check($url->host === "example.com", "host parsed");
$check($url->port === 8443, "port parsed as int");
$check($url->user === "user", "user parsed");
$check($url->password === "p@ss", "password parsed and percent-decoded");
$check($url->path === "/a/b.txt", "path parsed");
$check($url->query === "x=1&y=2", "query parsed");
$check($url->fragment === "frag", "fragment parsed");
$check($url->lastPathComponent === "b.txt", "lastPathComponent");
$check($url->pathExtension === "txt", "pathExtension");
$check($url->isFileURL === false, "https URL is not a file URL");

$check(new URL("https://example.com")->path === "", "URL without path has empty path");
$check(new URL("https://example.com")->port === null, "URL without port has null port");
$check(new URL("https://example.com")->query === null, "URL without query has null query");
$check(new URL("https://example.com/")->lastPathComponent === "/", "lastPathComponent of root path is /");

// ---------------------------------------------------------------------------
$section("percent-encoding");
// ---------------------------------------------------------------------------

// The raw string must be parsed before decoding; an encoded "#" in the query used to
// truncate the query and spill the rest into the fragment.
$encoded = new URL("https://example.com/search?q=a%23b");
$check($encoded->query === "q=a%23b", "encoded # in query keeps the query intact and raw");
$check($encoded->fragment === null, "encoded # in query does not create a fragment");

$encoded = new URL("https://example.com/a%20b?x=1");
$check($encoded->path === "/a b", "path is percent-decoded");
$check($encoded->absoluteString === "https://example.com/a%20b?x=1", "absoluteString keeps the encoding");
$check($encoded->removingPercentEncoding() === "https://example.com/a b?x=1", "removingPercentEncoding decodes");

$check(new URL("https://example.com/f#a%20b")->fragment === "a b", "fragment is percent-decoded");
$check(new URL("https://example.com/p?0")->query === "0", "a query of \"0\" is not treated as absent");

// The password must survive a construction round-trip without double encoding.
$check(new URL("https://u:p%40ss@example.com/")->absoluteString === "https://u:p%40ss@example.com/", "password keeps single encoding through the constructor");

// ---------------------------------------------------------------------------
$section("trailing slash / directory paths");
// ---------------------------------------------------------------------------

$check(new URL("https://example.com/a/b/")->absoluteString === "https://example.com/a/b/", "trailing slash survives construction");
$check(new URL("https://example.com/a/b/")->hasDirectoryPath === true, "trailing slash means directory path");
$check(new URL("https://example.com/a/b.txt")->hasDirectoryPath === false, "path with extension is not a directory path");
$check(new URL("https://example.com/api/users")->hasDirectoryPath === true, "extensionless remote path is treated as a directory path");
$check(new URL("https://example.com/x/")->pathComponents->array === ["/", "x", "/"], "pathComponents include leading and trailing slash");
$check(new URL("https://example.com/a/b")->pathComponents->array === ["/", "a", "b"], "pathComponents of plain path");

// ---------------------------------------------------------------------------
$section("appendPathComponent / appendingPathComponent");
// ---------------------------------------------------------------------------

$base = new URL("https://example.com/api?token=1");
$appended = $base->appendingPathComponent("users");
$check($appended->absoluteString === "https://example.com/api/users?token=1", "appendingPathComponent keeps the query");
$check($base->absoluteString === "https://example.com/api?token=1", "appendingPathComponent does not mutate the receiver");

$mutated = new URL("https://example.com/api");
$check($mutated->appendPathComponent("users") === $mutated, "appendPathComponent returns self");
$check($mutated->absoluteString === "https://example.com/api/users", "appendPathComponent mutates in place");

$check(new URL("https://example.com")->appendingPathComponent("a")->absoluteString === "https://example.com/a", "append to URL without path");
$check(new URL("https://example.com/a/")->appendingPathComponent("b")->path === "/a/b", "append to directory path adds no double slash");
$check(new URL("https://example.com/a")->appendingPathComponent("/b")->path === "/a/b", "leading slash of the component is normalized away");
$check(new URL("https://example.com/a")->appendingPathComponent("two words")->absoluteString === "https://example.com/a/two%20words", "component is percent-encoded");
$check(new URL("https://example.com/a")->appendingPathComponent("two words")->path === "/a/two words", "encoded component decodes back through path");
$check(new URL("https://example.com/a")->appendingPathComponent("b/c")->path === "/a/b/c", "multi-segment component is appended segment by segment");

// ---------------------------------------------------------------------------
$section("appendPathExtension / deletePathExtension");
// ---------------------------------------------------------------------------

$check(new URL("https://example.com/report?v=2")->appendingPathExtension("pdf")->absoluteString === "https://example.com/report.pdf?v=2", "appendingPathExtension keeps the query");
$check(new URL("https://example.com/report")->appendingPathExtension("")->absoluteString === "https://example.com/report", "empty extension is a no-op");
$check(new URL("https://example.com")->appendingPathExtension("pdf")->absoluteString === "https://example.com", "extension on a URL without path is a no-op");

$check(new URL("https://example.com/file.txt")->deletingPathExtension()->absoluteString === "https://example.com/file", "deletingPathExtension removes extension and dot");
$check(new URL("https://example.com/archive.tar.gz")->deletingPathExtension()->absoluteString === "https://example.com/archive.tar", "only the last extension is removed");
$check(new URL("https://example.com/dir/")->deletingPathExtension()->absoluteString === "https://example.com/dir/", "no extension means no change");
// The extension used to be str_replace()d out of the whole string, mangling a host that
// contains the same substring.
$check(new URL("https://txt.example.com/file.txt")->deletingPathExtension()->absoluteString === "https://txt.example.com/file", "host containing the extension substring is untouched");
$check(new URL("https://example.com/a.txt/")->deletingPathExtension()->path === "/a/", "trailing slash survives deletingPathExtension");

// ---------------------------------------------------------------------------
$section("deleteLastPathComponent / deletingLastPathComponent");
// ---------------------------------------------------------------------------

$check(new URL("https://example.com/a/b/c")->deletingLastPathComponent()->absoluteString === "https://example.com/a/b", "last component removed");
$check(new URL("https://example.com/a/b/")->deletingLastPathComponent()->absoluteString === "https://example.com/a", "trailing slash directory removed");
$check(new URL("https://example.com/a")->deletingLastPathComponent()->absoluteString === "https://example.com/", "single component leaves the root path");
$check(new URL("https://example.com/a/b?q=1#f")->deletingLastPathComponent()->absoluteString === "https://example.com/a?q=1#f", "query and fragment survive deletingLastPathComponent");
// The whole-string regex used to eat the host once the path ran out.
$check(new URL("https://example.com")->deletingLastPathComponent()->absoluteString === "https://example.com", "URL without path is unchanged, host survives");
$check(new URL("https://example.com/")->deletingLastPathComponent()->absoluteString === "https://example.com/", "root path is unchanged");

$immutable = new URL("https://example.com/a/b");
$immutable->deletingLastPathComponent();
$check($immutable->absoluteString === "https://example.com/a/b", "deletingLastPathComponent does not mutate the receiver");

// ---------------------------------------------------------------------------
$section("relative resolution (RFC 3986 section 5.4)");
// ---------------------------------------------------------------------------

$rfcBase = new URL("http://a/b/c/d?q");
$resolve = fn(string $reference): string => new URL($reference, $rfcBase)->absoluteString;

$check($resolve("g") === "http://a/b/c/g", "simple relative reference");
$check($resolve("./g") === "http://a/b/c/g", "./ reference");
$check($resolve("g/") === "http://a/b/c/g/", "relative directory reference keeps trailing slash");
$check($resolve("/g") === "http://a/g", "absolute-path reference replaces the whole path");
$check($resolve("//g") === "http://g", "network-path reference replaces the authority");
$check($resolve("?y") === "http://a/b/c/d?y", "query-only reference keeps the base path");
$check($resolve("g?y") === "http://a/b/c/g?y", "reference with query");
$check($resolve("#s") === "http://a/b/c/d?q#s", "fragment-only reference keeps path and query");
$check($resolve("g#s") === "http://a/b/c/g#s", "reference with fragment drops the base query");
$check($resolve("") === "http://a/b/c/d?q", "empty reference is the base itself");
$check($resolve(".") === "http://a/b/c/", "single dot reference");
$check($resolve("..") === "http://a/b/", "double dot reference");
$check($resolve("../g") === "http://a/b/g", "../ reference");
$check($resolve("../../g") === "http://a/g", "../../ reference");
$check($resolve("../../../g") === "http://a/g", "leading .. segments cannot climb above the root");
$check($resolve("g/../h") === "http://a/b/c/h", "inner dot segments are removed");
$check($resolve("https://other.example/x") === "https://other.example/x", "reference with scheme ignores the base");

$relative = new URL("g?y", $rfcBase);
$check($relative->relativeString === "g?y", "relativeString is the original reference");
$check($relative->relativePath === "g", "relativePath is the path of the reference");
$check($relative->absoluteString === "http://a/b/c/g?y", "absoluteString resolves against the base");
$check($relative->path === "/b/c/g", "component getters read the resolved URL");
$check($rfcBase->relativeString === $rfcBase->absoluteString, "relativeString of an absolute URL is its absoluteString");
$check($rfcBase->relativePath === "/b/c/d", "relativePath of an absolute URL is its path");

// A mutation resolves the URL: no double resolution against a stale baseURL afterwards.
$mutatedRelative = new URL("g", $rfcBase);
$mutatedRelative->appendPathComponent("x");
$check($mutatedRelative->absoluteString === "http://a/b/c/g/x", "mutating a relative URL resolves it exactly once");
$check($mutatedRelative->baseURL === null, "mutating a relative URL detaches it from its base");

// ---------------------------------------------------------------------------
$section("standardized");
// ---------------------------------------------------------------------------

$check(new URL("https://example.com/a/b/../c/./d")->standardized->absoluteString === "https://example.com/a/c/d", "dot segments are removed lexically");
$check(new URL("https://example.com/a/../../b")->standardized->absoluteString === "https://example.com/b", "extra .. segments stop at the root");
$check(new URL("https://example.com/a/b?q=./x")->standardized->query === "q=./x", "standardize touches only the path");

$standardizeSource = new URL("https://example.com/a/../b");
$standardizeSource->standardized;
$check($standardizeSource->absoluteString === "https://example.com/a/../b", "standardized does not mutate the receiver");

// ---------------------------------------------------------------------------
$section("compare / isEqual");
// ---------------------------------------------------------------------------

$check(new URL("https://EXAMPLE.com/a")->isEqual(new URL("https://example.com/a")), "host comparison is case-insensitive");
$check(!new URL("https://example.com/A")->isEqual(new URL("https://example.com/a")), "path comparison is case-sensitive for remote URLs");
$check(!new URL("https://example.com/a?x=1")->isEqual(new URL("https://example.com/a?x=2")), "query participates in equality");
$check(URL::fileURL("C:/Temp/Data")->isEqual(URL::fileURL("c:/temp/data")), "file URL paths compare case-insensitively");
$check(!new URL("https://example.com/a")->isEqual("https://example.com/a"), "a non-URL value is never equal");

// ---------------------------------------------------------------------------
$section("file URLs and the filesystem");
// ---------------------------------------------------------------------------

$check(URL::fileURL("C:\\Temp\\Nested\\file.txt")->absoluteString === "file:///C:/Temp/Nested/file.txt", "backslashes are converted");
$check(URL::fileURL("C:/Temp/My Files")->absoluteString === "file:///C:/Temp/My%20Files", "file URL path is percent-encoded");
// parse_url() on Windows strips the slash before the drive letter, so accept both forms.
$check(in_array(URL::fileURL("C:/Temp/My Files")->path, ["C:/Temp/My Files", "/C:/Temp/My Files"], true), "file URL path decodes back");
$check(URL::fileURL("C:/Temp")->isFileURL === true, "fileURL creates a file URL");

// A UNC path becomes an authority-form file URL and its native path restores the authority.
$unc = URL::fileURL("\\\\server\\share\\file.txt");
$check($unc->absoluteString === "file://server/share/file.txt", "UNC path becomes an authority-form file URL");
$check($unc->host === "server", "the UNC server is the URL host");
$check($unc->path === "/share/file.txt", "the UNC share is the URL path");
$check($unc->fileSystemRepresentation === "//server/share/file.txt", "fileSystemRepresentation restores the UNC authority");

// The scheme is case-insensitive per RFC 3986; it is canonicalized to lowercase.
$check(new URL("HTTPS://EXAMPLE.com/Path")->scheme === "https", "an uppercase scheme parses and canonicalizes");
$check(new URL("HTTPS://EXAMPLE.com/Path")->host === "EXAMPLE.com", "only the scheme is canonicalized, not the host string");
$check(new URL("FILE:///C:/Temp/x.txt")->isFileURL === true, "isFileURL holds for an uppercase scheme");

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-url-test-" . getmypid();
mkdir($temporaryDirectory);
file_put_contents($temporaryDirectory . DIRECTORY_SEPARATOR . "file.txt", "x");
try {
    $directoryURL = URL::fileURL($temporaryDirectory);
    $check($directoryURL->hasDirectoryPath === true, "existing directory has a directory path");

    $fileURL = $directoryURL->appendingPathComponent("file.txt");
    $check($fileURL->hasDirectoryPath === false, "existing file does not have a directory path");
    $check($fileURL->lastPathComponent === "file.txt", "appending onto a real directory keeps the component");

    $expected = realpath($temporaryDirectory . DIRECTORY_SEPARATOR . "file.txt");
    $check($expected !== false && $fileURL->fileSystemRepresentation === $expected, "fileSystemRepresentation of an existing file is its real path");

    // A missing file must produce a usable native path instead of a TypeError from getRealPath().
    $missing = $directoryURL->appendingPathComponent("missing.bin");
    $check(str_ends_with($missing->fileSystemRepresentation, "missing.bin"), "fileSystemRepresentation of a missing file falls back to the native path");

    // resolveSymlinksInPath used readlink(), which warns and returns false on any regular file,
    // reducing the URL to "file:///".
    $resolved = $fileURL->resolvingSymlinksInPath();
    $check($resolved->lastPathComponent === "file.txt", "resolvingSymlinksInPath keeps a regular file intact");
    $check($resolved->isFileURL === true, "resolvingSymlinksInPath keeps the file scheme");
    $check($missing->resolvingSymlinksInPath()->lastPathComponent === "missing.bin", "resolvingSymlinksInPath leaves a missing path unchanged");
    $check(new URL("https://example.com/a")->resolvingSymlinksInPath()->absoluteString === "https://example.com/a", "resolvingSymlinksInPath ignores remote URLs");

    try {
        $fileURL->appendingPathComponent("child");
        $check(false, "appending to an existing regular file must fail");
    } catch (InternalInconsistencyException) {
        $check(true, "appending to an existing regular file fails");
    }
} finally {
    @unlink($temporaryDirectory . DIRECTORY_SEPARATOR . "file.txt");
    @rmdir($temporaryDirectory);
}

// ---------------------------------------------------------------------------
$section("construction and serialization");
// ---------------------------------------------------------------------------

try {
    new URL("definitely not a url");
    $check(false, "constructing from a non-URL string must fail");
} catch (InternalInconsistencyException) {
    $check(true, "constructing from a non-URL string fails");
}

$original = new URL("https://user:pw@example.com:8080/a/b?x=1#f");
/** @var URL $restored */
$restored = unserialize(serialize($original));
$check($restored->absoluteString === $original->absoluteString, "serialization round-trips the string");
$check($restored->host === "example.com" && $restored->port === 8080, "components are readable after unserialize");

$relativeRestored = unserialize(serialize(new URL("g", $rfcBase)));
$check($relativeRestored instanceof URL && $relativeRestored->absoluteString === "http://a/b/c/g", "relative URL survives serialization with its base");

$check((string)new URL("https://example.com/a")->description === "https://example.com/a", "description is the absolute string");
$check(new URL("https://example.com/a")->jsonSerialize() === "https://example.com/a", "jsonSerialize is the absolute string");

URLTestRunner::finish();
