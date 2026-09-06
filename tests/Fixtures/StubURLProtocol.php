<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests\Fixtures;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLCacheStoragePolicy;
use Sabatier\Foundation\Networking\URLProtocol;
use Sabatier\Foundation\Networking\URLRequest;

/** @internal */
final class StubURLProtocol extends URLProtocol
{
    public int $startCount = 0;
    public int $stopCount = 0;

    #[Override]
    public static function canInit(URLRequest $request): bool
    {
        return $request->url->host === "stub.example.com";
    }

    #[Override]
    public static function canonicalRequest(URLRequest $request): URLRequest
    {
        return $request;
    }

    #[Override]
    public function startLoading(): void
    {
        $this->startCount += 1;
    }

    #[Override]
    public function stopLoading(): void
    {
        $this->stopCount += 1;
    }

    /** @param ArrayClass<string> $chunks */
    public function respond(ArrayClass $chunks): void
    {
        $this->client?->urlProtocolDidReceiveCacheStoragePolicy($this, new HTTPURLResponse($this->request->url), URLCacheStoragePolicy::notAllowed);
        $chunks->forEach(fn(string $chunk) => $this->client?->urlProtocolDidLoad($this, $chunk));
        $this->client?->urlProtocolDidFinishLoading($this);
    }

    public function fail(Error $error): void
    {
        $this->client?->urlProtocolDidFailWithError($this, $error);
    }
}
