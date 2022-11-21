<?php

namespace Sabatier\Foundation\Test;

use Exception;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLResponse;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\URL;

final class URLSessionTest extends TestCase
{
    public URL $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->url = new URL('https://api.ipify.org/?format=json');
    }

    /**
     * @throws Exception
     */
    public function testCanExecuteDataTask(): void
    {
        URLSession::shared()->dataTaskWithURL($this->url, function (?string $data, ?URLResponse $response, ?Error $error): void {
            self::assertNotEmpty($data);
            self::assertNull($error);
            self::assertInstanceOf(
                HTTPURLResponse::class,
                $response
            );
            self::assertNotEmpty($data);
        })->resume();
    }

    /**
     * @throws Exception
     */
    public function testCanExecuteDownloadTask(): void
    {
        URLSession::shared()->downloadTaskWithURL($this->url, function (?URL $url, ?URLResponse $response, ?Error $error): void {
            self::assertNull($error);
            self::assertInstanceOf(
                HTTPURLResponse::class,
                $response
            );
            self::assertNotNull($url);
            self::assertFileExists($url->path);
        })->resume();
    }
}
