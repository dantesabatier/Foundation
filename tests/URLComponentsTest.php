<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;

/**
 * Tests for src/URLComponents.php.
 *
 * The query-item parsing section is a regression guard: a query pair must split
 * on the first "=" only, a valueless parameter must not raise a warning, an empty
 * value must be preserved as distinct from an absent one, and a duplicated name
 * must yield one item per occurrence.
 */
final class URLComponentsTest extends TestCase
{
    /**
     * Turns the query items of the given URL into a plain [name => value, ...] list of pairs,
     * preserving order and duplicates.
     *
     * @return list<array{string, string|null}>
     */
    private static function queryPairs(string $url): array
    {
        $items = new URLComponents($url)->queryItems;
        if ($items === null) {
            return [];
        }
        return $items->map(fn(URLQueryItem $item): array => [$item->name, $item->value])->array;
    }

    public function testConstructorParsesComponents(): void
    {
        $components = new URLComponents("https://user:secret@example.com:8443/a/b?x=1#frag");
        $this->assertSame("https", $components->scheme, "scheme parsed");
        $this->assertSame("user", $components->user, "user parsed");
        $this->assertSame("secret", $components->password, "password parsed (pass mapped to password)");
        $this->assertSame("example.com", $components->host, "host parsed");
        $this->assertSame(8443, $components->port, "port parsed as int");
        $this->assertSame("/a/b", $components->path, "path parsed");
        $this->assertSame("x=1", $components->query, "query parsed");
        $this->assertSame("frag", $components->fragment, "fragment parsed");
    }

    public function testEmptyComponents(): void
    {
        $empty = new URLComponents();
        $this->assertNull($empty->scheme, "empty components have null scheme");
        $this->assertNull($empty->queryItems, "no query means null queryItems");
        $this->assertNull($empty->string, "empty components produce null string");
    }

    /**
     * @return array<string, array{string, list<array{string, string|null}>, string}>
     */
    public static function queryItemVectors(): array
    {
        return [
            "two pairs" => ["https://x.test/p?a=1&b=2", [["a", "1"], ["b", "2"]], "two simple pairs"],
            "duplicate name" => ["https://x.test/p?a=1&a=2", [["a", "1"], ["a", "2"]], "duplicate name yields one item per occurrence, in order"],
            "valueless" => ["https://x.test/p?flag", [["flag", null]], "valueless parameter has null value, no warning"],
            "empty value" => ["https://x.test/p?a=", [["a", ""]], "empty value is preserved and distinct from absent"],
            "mixed" => ["https://x.test/p?token=abc&admin", [["token", "abc"], ["admin", null]], "mixed valued and valueless params"],
            "value with equals" => ["https://x.test/p?sig=a=b=c", [["sig", "a=b=c"]], "split on first equals only; value may contain equals"],
            "encoded space" => ["https://x.test/p?n=a%20b", [["n", "a b"]], "percent-encoded space decoded in value"],
        ];
    }

    /**
     * @param list<array{string, string|null}> $expected
     */
    #[DataProvider("queryItemVectors")]
    public function testQueryItemsParsing(string $url, array $expected, string $message): void
    {
        $this->assertSame($expected, self::queryPairs($url), $message);
    }

    public function testQueryItemValueIsHTMLEscapedAfterDecoding(): void
    {
        $decoded = self::queryPairs("https://x.test/p?q=%3Cscript%3E");
        $this->assertSame("&lt;script&gt;", $decoded[0][1], "value is HTML-escaped after decoding");
    }

    public function testSettingQueryItemsBuildsTheQueryString(): void
    {
        $roundTrip = new URLComponents();
        $roundTrip->queryItems = new ArrayClass([new URLQueryItem("a", "1"), new URLQueryItem("b", "two words")]);
        $this->assertNotNull($roundTrip->query, "set queryItems builds query string");
        $this->assertStringContainsString("a=1", $roundTrip->query, "set queryItems builds query string");
        $this->assertStringContainsString("b=", $roundTrip->query, "set queryItems includes second key");
        $this->assertStringContainsString("b=two+words", $roundTrip->query, "set queryItems preserves the parameter name, not a numeric index");
        $this->assertStringNotContainsString("0=", $roundTrip->query, "set queryItems does not emit numeric index keys");
    }

    public function testGetThenSetRoundTripsTheQueryString(): void
    {
        // Full round-trip: parsing a query and re-emitting it keeps names and values.
        $source = new URLComponents("https://x.test/p?a=1&b=2");
        $rebuilt = new URLComponents();
        $rebuilt->queryItems = $source->queryItems;
        $this->assertSame("a=1&b=2", $rebuilt->query, "get then set round-trips the query string");
    }

    public function testStringReassembly(): void
    {
        $reassembled = new URLComponents("https://example.com/path?x=1#frag");
        $this->assertNotNull($reassembled->string, "string keeps scheme and host");
        $this->assertStringStartsWith("https://example.com", $reassembled->string, "string keeps scheme and host");
        $this->assertStringContainsString("?x=1", $reassembled->string, "string keeps query");
        $this->assertStringContainsString("#frag", $reassembled->string, "string keeps fragment");
    }

    public function testTrailingPathSlashIsPreservedInReassembly(): void
    {
        // A trailing path slash used to be lost in reassembly, which broke directory URLs.
        $this->assertSame("https://example.com/a/b/", new URLComponents("https://example.com/a/b/")->string, "trailing path slash is preserved");
        $this->assertSame("https://example.com/", new URLComponents("https://example.com/")->string, "root path does not become a double slash");
    }

    public function testPasswordKeepsSingleEncodingThroughReassembly(): void
    {
        // An already percent-encoded password used to be encoded a second time.
        $this->assertSame("https://u:p%40ss@example.com/x", new URLComponents("https://u:p%40ss@example.com/x")->string, "password keeps single encoding through reassembly");
    }

    public function testSchemeIsCanonicalizedToLowercase(): void
    {
        // The scheme is case-insensitive per RFC 3986 and is canonicalized to lowercase on parse.
        $this->assertSame("https", new URLComponents("HTTPS://example.com/a")->scheme, "the scheme is canonicalized to lowercase");
    }

    public function testQueryOfZeroSurvives(): void
    {
        // "0" is a valid value for a component; empty() used to swallow it.
        $zero = new URLComponents("https://example.com/p?0");
        $this->assertSame("0", $zero->query, "a query of \"0\" survives parsing");
        $this->assertSame("https://example.com/p?0", $zero->string, "a query of \"0\" survives reassembly");
    }
}
