<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\URL;

/**
 * Tests for src/URL.php.
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
final class URLTest extends TestCase
{
    private const string RFC_BASE = "http://a/b/c/d?q";

    private ?string $temporaryDirectory = null;

    #[Override]
    protected function tearDown(): void
    {
        if ($this->temporaryDirectory !== null) {
            @unlink($this->temporaryDirectory . DIRECTORY_SEPARATOR . "file.txt");
            @rmdir($this->temporaryDirectory);
            $this->temporaryDirectory = null;
        }
    }

    private function rfcBase(): URL
    {
        return new URL(self::RFC_BASE);
    }

    /**
     * Creates a temporary directory containing one regular file ("file.txt") and returns its
     * path. tearDown() guarantees the cleanup even when an assertion fails.
     */
    private function makeTemporaryDirectory(): string
    {
        $this->temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-url-test-" . getmypid();
        mkdir($this->temporaryDirectory);
        file_put_contents($this->temporaryDirectory . DIRECTORY_SEPARATOR . "file.txt", "x");
        return $this->temporaryDirectory;
    }

    public function testComponentParsing(): void
    {
        $url = new URL("https://user:p%40ss@example.com:8443/a/b.txt?x=1&y=2#frag");
        $this->assertSame("https", $url->scheme, "scheme parsed");
        $this->assertSame("example.com", $url->host, "host parsed");
        $this->assertSame(8443, $url->port, "port parsed as int");
        $this->assertSame("user", $url->user, "user parsed");
        $this->assertSame("p@ss", $url->password, "password parsed and percent-decoded");
        $this->assertSame("/a/b.txt", $url->path, "path parsed");
        $this->assertSame("x=1&y=2", $url->query, "query parsed");
        $this->assertSame("frag", $url->fragment, "fragment parsed");
        $this->assertSame("b.txt", $url->lastPathComponent, "lastPathComponent");
        $this->assertSame("txt", $url->pathExtension, "pathExtension");
        $this->assertFalse($url->isFileURL, "https URL is not a file URL");
    }

    public function testMissingComponents(): void
    {
        $this->assertSame("", new URL("https://example.com")->path, "URL without path has empty path");
        $this->assertNull(new URL("https://example.com")->port, "URL without port has null port");
        $this->assertNull(new URL("https://example.com")->query, "URL without query has null query");
        $this->assertSame("/", new URL("https://example.com/")->lastPathComponent, "lastPathComponent of root path is /");
    }

    public function testPercentEncoding(): void
    {
        // The raw string must be parsed before decoding; an encoded "#" in the query used to truncate the query and spill the rest into the fragment.
        $encoded = new URL("https://example.com/search?q=a%23b");
        $this->assertSame("q=a%23b", $encoded->query, "encoded # in query keeps the query intact and raw");
        $this->assertNull($encoded->fragment, "encoded # in query does not create a fragment");

        $encoded = new URL("https://example.com/a%20b?x=1");
        $this->assertSame("/a b", $encoded->path, "path is percent-decoded");
        $this->assertSame("https://example.com/a%20b?x=1", $encoded->absoluteString, "absoluteString keeps the encoding");
        $this->assertSame("https://example.com/a b?x=1", $encoded->removingPercentEncoding(), "removingPercentEncoding decodes");

        $this->assertSame("a b", new URL("https://example.com/f#a%20b")->fragment, "fragment is percent-decoded");
        $this->assertSame("0", new URL("https://example.com/p?0")->query, "a query of \"0\" is not treated as absent");

        // The password must survive a construction round-trip without double encoding.
        $this->assertSame("https://u:p%40ss@example.com/", new URL("https://u:p%40ss@example.com/")->absoluteString, "password keeps single encoding through the constructor");
    }

    public function testTrailingSlashAndDirectoryPaths(): void
    {
        $this->assertSame("https://example.com/a/b/", new URL("https://example.com/a/b/")->absoluteString, "trailing slash survives construction");
        $this->assertTrue(new URL("https://example.com/a/b/")->hasDirectoryPath, "trailing slash means directory path");
        $this->assertFalse(new URL("https://example.com/a/b.txt")->hasDirectoryPath, "path with extension is not a directory path");
        $this->assertTrue(new URL("https://example.com/api/users")->hasDirectoryPath, "extensionless remote path is treated as a directory path");
        $this->assertSame(["/", "x", "/"], new URL("https://example.com/x/")->pathComponents->array, "pathComponents include leading and trailing slash");
        $this->assertSame(["/", "a", "b"], new URL("https://example.com/a/b")->pathComponents->array, "pathComponents of plain path");
    }

    public function testAppendingPathComponent(): void
    {
        $base = new URL("https://example.com/api?token=1");
        $appended = $base->appendingPathComponent("users");
        $this->assertSame("https://example.com/api/users?token=1", $appended->absoluteString, "appendingPathComponent keeps the query");
        $this->assertSame("https://example.com/api?token=1", $base->absoluteString, "appendingPathComponent does not mutate the receiver");

        $this->assertSame("https://example.com/a", new URL("https://example.com")->appendingPathComponent("a")->absoluteString, "append to URL without path");
        $this->assertSame("/a/b", new URL("https://example.com/a/")->appendingPathComponent("b")->path, "append to directory path adds no double slash");
        $this->assertSame("/a/b", new URL("https://example.com/a")->appendingPathComponent("/b")->path, "leading slash of the component is normalized away");
        $this->assertSame("https://example.com/a/two%20words", new URL("https://example.com/a")->appendingPathComponent("two words")->absoluteString, "component is percent-encoded");
        $this->assertSame("/a/two words", new URL("https://example.com/a")->appendingPathComponent("two words")->path, "encoded component decodes back through path");
        $this->assertSame("/a/b/c", new URL("https://example.com/a")->appendingPathComponent("b/c")->path, "multi-segment component is appended segment by segment");
    }

    public function testAppendPathComponentMutatesInPlace(): void
    {
        $mutated = new URL("https://example.com/api");
        $this->assertSame($mutated, $mutated->appendPathComponent("users"), "appendPathComponent returns self");
        $this->assertSame("https://example.com/api/users", $mutated->absoluteString, "appendPathComponent mutates in place");
    }

    public function testAppendingPathExtension(): void
    {
        $this->assertSame("https://example.com/report.pdf?v=2", new URL("https://example.com/report?v=2")->appendingPathExtension("pdf")->absoluteString, "appendingPathExtension keeps the query");
        $this->assertSame("https://example.com/report", new URL("https://example.com/report")->appendingPathExtension("")->absoluteString, "empty extension is a no-op");
        $this->assertSame("https://example.com", new URL("https://example.com")->appendingPathExtension("pdf")->absoluteString, "extension on a URL without path is a no-op");
    }

    public function testDeletingPathExtension(): void
    {
        $this->assertSame("https://example.com/file", new URL("https://example.com/file.txt")->deletingPathExtension()->absoluteString, "deletingPathExtension removes extension and dot");
        $this->assertSame("https://example.com/archive.tar", new URL("https://example.com/archive.tar.gz")->deletingPathExtension()->absoluteString, "only the last extension is removed");
        $this->assertSame("https://example.com/dir/", new URL("https://example.com/dir/")->deletingPathExtension()->absoluteString, "no extension means no change");
        // The extension used to be str_replace()d out of the whole string, mangling a host that contains the same substring.
        $this->assertSame("https://txt.example.com/file", new URL("https://txt.example.com/file.txt")->deletingPathExtension()->absoluteString, "host containing the extension substring is untouched");
        $this->assertSame("/a/", new URL("https://example.com/a.txt/")->deletingPathExtension()->path, "trailing slash survives deletingPathExtension");
    }

    public function testDeletingLastPathComponent(): void
    {
        $this->assertSame("https://example.com/a/b", new URL("https://example.com/a/b/c")->deletingLastPathComponent()->absoluteString, "last component removed");
        $this->assertSame("https://example.com/a", new URL("https://example.com/a/b/")->deletingLastPathComponent()->absoluteString, "trailing slash directory removed");
        $this->assertSame("https://example.com/", new URL("https://example.com/a")->deletingLastPathComponent()->absoluteString, "single component leaves the root path");
        $this->assertSame("https://example.com/a?q=1#f", new URL("https://example.com/a/b?q=1#f")->deletingLastPathComponent()->absoluteString, "query and fragment survive deletingLastPathComponent");
        // The whole-string regex used to eat the host once the path ran out.
        $this->assertSame("https://example.com", new URL("https://example.com")->deletingLastPathComponent()->absoluteString, "URL without path is unchanged, host survives");
        $this->assertSame("https://example.com/", new URL("https://example.com/")->deletingLastPathComponent()->absoluteString, "root path is unchanged");

        $immutable = new URL("https://example.com/a/b");
        $immutable->deletingLastPathComponent();
        $this->assertSame("https://example.com/a/b", $immutable->absoluteString, "deletingLastPathComponent does not mutate the receiver");
    }

    /**
     * RFC 3986 section 5.4 reference-resolution vectors, resolved against "http://a/b/c/d?q".
     *
     * @return array<string, array{string, string, string}>
     */
    public static function rfc3986ResolutionVectors(): array
    {
        return [
            "simple" => ["g", "http://a/b/c/g", "simple relative reference"],
            "current directory" => ["./g", "http://a/b/c/g", "./ reference"],
            "directory" => ["g/", "http://a/b/c/g/", "relative directory reference keeps trailing slash"],
            "absolute path" => ["/g", "http://a/g", "absolute-path reference replaces the whole path"],
            "network path" => ["//g", "http://g", "network-path reference replaces the authority"],
            "query only" => ["?y", "http://a/b/c/d?y", "query-only reference keeps the base path"],
            "with query" => ["g?y", "http://a/b/c/g?y", "reference with query"],
            "fragment only" => ["#s", "http://a/b/c/d?q#s", "fragment-only reference keeps path and query"],
            "with fragment" => ["g#s", "http://a/b/c/g#s", "reference with fragment drops the base query"],
            "empty" => ["", "http://a/b/c/d?q", "empty reference is the base itself"],
            "single dot" => [".", "http://a/b/c/", "single dot reference"],
            "double dot" => ["..", "http://a/b/", "double dot reference"],
            "parent" => ["../g", "http://a/b/g", "../ reference"],
            "grandparent" => ["../../g", "http://a/g", "../../ reference"],
            "above root" => ["../../../g", "http://a/g", "leading .. segments cannot climb above the root"],
            "inner dots" => ["g/../h", "http://a/b/c/h", "inner dot segments are removed"],
            "with scheme" => ["https://other.example/x", "https://other.example/x", "reference with scheme ignores the base"],
        ];
    }

    #[DataProvider("rfc3986ResolutionVectors")]
    public function testRelativeResolution(string $reference, string $expected, string $message): void
    {
        $this->assertSame($expected, new URL($reference, $this->rfcBase())->absoluteString, $message);
    }

    public function testRelativeURLProperties(): void
    {
        $rfcBase = $this->rfcBase();
        $relative = new URL("g?y", $rfcBase);
        $this->assertSame("g?y", $relative->relativeString, "relativeString is the original reference");
        $this->assertSame("g", $relative->relativePath, "relativePath is the path of the reference");
        $this->assertSame("http://a/b/c/g?y", $relative->absoluteString, "absoluteString resolves against the base");
        $this->assertSame("/b/c/g", $relative->path, "component getters read the resolved URL");
        $this->assertSame($rfcBase->absoluteString, $rfcBase->relativeString, "relativeString of an absolute URL is its absoluteString");
        $this->assertSame("/b/c/d", $rfcBase->relativePath, "relativePath of an absolute URL is its path");
    }

    public function testMutatingARelativeURLResolvesItExactlyOnce(): void
    {
        // A mutation resolves the URL: no double resolution against a stale baseURL afterwards.
        $mutatedRelative = new URL("g", $this->rfcBase());
        $mutatedRelative->appendPathComponent("x");
        $this->assertSame("http://a/b/c/g/x", $mutatedRelative->absoluteString, "mutating a relative URL resolves it exactly once");
        $this->assertNull($mutatedRelative->baseURL, "mutating a relative URL detaches it from its base");
    }

    public function testStandardized(): void
    {
        $this->assertSame("https://example.com/a/c/d", new URL("https://example.com/a/b/../c/./d")->standardized->absoluteString, "dot segments are removed lexically");
        $this->assertSame("https://example.com/b", new URL("https://example.com/a/../../b")->standardized->absoluteString, "extra .. segments stop at the root");
        $this->assertSame("q=./x", new URL("https://example.com/a/b?q=./x")->standardized->query, "standardize touches only the path");

        $standardizeSource = new URL("https://example.com/a/../b");
        $standardizeSource->standardized;
        $this->assertSame("https://example.com/a/../b", $standardizeSource->absoluteString, "standardized does not mutate the receiver");
    }

    public function testCompareAndIsEqual(): void
    {
        $this->assertTrue(new URL("https://EXAMPLE.com/a")->isEqual(new URL("https://example.com/a")), "host comparison is case-insensitive");
        $this->assertFalse(new URL("https://example.com/A")->isEqual(new URL("https://example.com/a")), "path comparison is case-sensitive for remote URLs");
        $this->assertFalse(new URL("https://example.com/a?x=1")->isEqual(new URL("https://example.com/a?x=2")), "query participates in equality");
        $this->assertTrue(URL::fileURL("C:/Temp/Data")->isEqual(URL::fileURL("c:/temp/data")), "file URL paths compare case-insensitively");
        $this->assertFalse(new URL("https://example.com/a")->isEqual("https://example.com/a"), "a non-URL value is never equal");
    }

    public function testFileURLConstruction(): void
    {
        $this->assertSame("file:///C:/Temp/Nested/file.txt", URL::fileURL("C:\\Temp\\Nested\\file.txt")->absoluteString, "backslashes are converted");
        $this->assertSame("file:///C:/Temp/My%20Files", URL::fileURL("C:/Temp/My Files")->absoluteString, "file URL path is percent-encoded");
        // parse_url() on Windows strips the slash before the drive letter, so accept both forms.
        $this->assertContains(URL::fileURL("C:/Temp/My Files")->path, ["C:/Temp/My Files", "/C:/Temp/My Files"], "file URL path decodes back");
        $this->assertTrue(URL::fileURL("C:/Temp")->isFileURL, "fileURL creates a file URL");
    }

    public function testUncPath(): void
    {
        // A UNC path becomes an authority-form file URL and its native path restores the authority.
        $unc = URL::fileURL("\\\\server\\share\\file.txt");
        $this->assertSame("file://server/share/file.txt", $unc->absoluteString, "UNC path becomes an authority-form file URL");
        $this->assertSame("server", $unc->host, "the UNC server is the URL host");
        $this->assertSame("/share/file.txt", $unc->path, "the UNC share is the URL path");
        $this->assertSame("//server/share/file.txt", $unc->fileSystemRepresentation, "fileSystemRepresentation restores the UNC authority");
    }

    public function testSchemeCanonicalization(): void
    {
        // The scheme is case-insensitive per RFC 3986; it is canonicalized to lowercase.
        $this->assertSame("https", new URL("HTTPS://EXAMPLE.com/Path")->scheme, "an uppercase scheme parses and canonicalizes");
        $this->assertSame("EXAMPLE.com", new URL("HTTPS://EXAMPLE.com/Path")->host, "only the scheme is canonicalized, not the host string");
        $this->assertTrue(new URL("FILE:///C:/Temp/x.txt")->isFileURL, "isFileURL holds for an uppercase scheme");
    }

    public function testFilesystemBackedBehaviour(): void
    {
        $temporaryDirectory = $this->makeTemporaryDirectory();

        $directoryURL = URL::fileURL($temporaryDirectory);
        $this->assertTrue($directoryURL->hasDirectoryPath, "existing directory has a directory path");

        $fileURL = $directoryURL->appendingPathComponent("file.txt");
        $this->assertFalse($fileURL->hasDirectoryPath, "existing file does not have a directory path");
        $this->assertSame("file.txt", $fileURL->lastPathComponent, "appending onto a real directory keeps the component");

        $expected = realpath($temporaryDirectory . DIRECTORY_SEPARATOR . "file.txt");
        $this->assertNotFalse($expected, "fileSystemRepresentation of an existing file is its real path");
        $this->assertSame($expected, $fileURL->fileSystemRepresentation, "fileSystemRepresentation of an existing file is its real path");

        // A missing file must produce a usable native path instead of a TypeError from getRealPath().
        $missing = $directoryURL->appendingPathComponent("missing.bin");
        $this->assertStringEndsWith("missing.bin", $missing->fileSystemRepresentation, "fileSystemRepresentation of a missing file falls back to the native path");

        // resolveSymlinksInPath used readlink(), which warns and returns false on any regular file, reducing the URL to "file:///".
        $resolved = $fileURL->resolvingSymlinksInPath();
        $this->assertSame("file.txt", $resolved->lastPathComponent, "resolvingSymlinksInPath keeps a regular file intact");
        $this->assertTrue($resolved->isFileURL, "resolvingSymlinksInPath keeps the file scheme");
        $this->assertSame("missing.bin", $missing->resolvingSymlinksInPath()->lastPathComponent, "resolvingSymlinksInPath leaves a missing path unchanged");
        $this->assertSame("https://example.com/a", new URL("https://example.com/a")->resolvingSymlinksInPath()->absoluteString, "resolvingSymlinksInPath ignores remote URLs");
    }

    public function testAppendingToAnExistingRegularFileFails(): void
    {
        $temporaryDirectory = $this->makeTemporaryDirectory();
        $fileURL = URL::fileURL($temporaryDirectory)->appendingPathComponent("file.txt");

        // "appending to an existing regular file fails"
        $this->expectException(InternalInconsistencyException::class);
        $fileURL->appendingPathComponent("child");
    }

    public function testConstructingFromANonURLStringFails(): void
    {
        // "constructing from a non-URL string fails"
        $this->expectException(InternalInconsistencyException::class);
        new URL("definitely not a url");
    }

    public function testSerialization(): void
    {
        $original = new URL("https://user:pw@example.com:8080/a/b?x=1#f");
        /** @var URL $restored */
        $restored = unserialize(serialize($original));
        $this->assertSame($original->absoluteString, $restored->absoluteString, "serialization round-trips the string");
        $this->assertSame("example.com", $restored->host, "components are readable after unserialize");
        $this->assertSame(8080, $restored->port, "components are readable after unserialize");

        $relativeRestored = unserialize(serialize(new URL("g", $this->rfcBase())));
        $this->assertInstanceOf(URL::class, $relativeRestored, "relative URL survives serialization with its base");
        $this->assertSame("http://a/b/c/g", $relativeRestored->absoluteString, "relative URL survives serialization with its base");

        $this->assertSame("https://example.com/a", (string)new URL("https://example.com/a")->description, "description is the absolute string");
        $this->assertSame("https://example.com/a", new URL("https://example.com/a")->jsonSerialize(), "jsonSerialize is the absolute string");
    }
}
