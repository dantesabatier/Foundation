<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Networking\FTPURLProtocol;
use Sabatier\Foundation\Networking\TaskBody;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionDataTask;
use Sabatier\Foundation\URL;

/**
 * Tests for src/Networking/FTPURLProtocol.php.
 *
 * Regression guards:
 *  - fwrite() returns 0 for an empty string, which is a successful write and must
 *    not make FTPURLProtocol treat an empty upload body as a file-system error.
 */
final class FTPURLProtocolTest extends TestCase
{
    public function testEmptyDataBodyCanBeConfiguredForUpload(): void
    {
        $request = new URLRequest(new URL("ftp://localhost/upload"));
        $body = TaskBody::data("");
        $task = new URLSessionDataTask(URLSession::shared(), $request, 1, $body);
        $protocol = new FTPURLProtocol($task);

        $protocol->configureEasyHandle($request, $body);

        $this->assertSame(0.0, $task->countOfBytesExpectedToSend, "an empty upload body is configured without treating fwrite() returning 0 as an error");
    }
}
