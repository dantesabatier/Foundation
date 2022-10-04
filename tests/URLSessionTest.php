<?php

namespace Sabatier\Foundation\Test;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\HTTPURLResponse;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLResponse;
use Sabatier\Foundation\URLSession;

final class URLSessionTest extends TestCase
{
    public URL $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->url = new URL('https://api.ipify.org/?format=json');
    }

    public function testCanFetch(): void
    {
        $task = URLSession::shared()->dataTaskWithURL($this->url, function (?string $data, ?URLResponse $response, ?Error $error): void {
            self::assertNull($error);
            self::assertInstanceOf(
                HTTPURLResponse::class,
                $response
            );
            self::assertNotEmpty($data);
        });
        $task->resume();
    }

    public function testCanDownload(): void
    {
        $task = URLSession::shared()->downloadTaskWithURL($this->url, function (?URL $url, ?URLResponse $response, ?Error $error): void {
            self::assertNull($error);
            self::assertInstanceOf(
                HTTPURLResponse::class,
                $response
            );
            self::assertNotNull($url);
            self::assertTrue($url->isFileURL);
            self::assertFileExists($url->path);
        });
        $task->resume();
    }
}
