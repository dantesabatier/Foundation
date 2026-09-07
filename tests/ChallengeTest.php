<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Networking\Challenge;

/**
 * Commas inside quoted parameters are data; commas between schemes must not mix their realms or duplicate challenges.
 */
final class ChallengeTest extends TestCase
{
    public function testParametersBelongToTheirOwnChallenge(): void
    {
        $challenges = Challenge::challengesFromAuthenticateFieldValue("Digest realm=\"first\", nonce=\"a=b\", Basic realm=\"second\"");
        $this->assertSame(2, $challenges->count);
        $this->assertSame("Digest", $challenges[0]->authScheme);
        $this->assertSame("first", $challenges[0]->parameter("realm"));
        $this->assertSame("a=b", $challenges[0]->parameter("nonce"));
        $this->assertSame("Basic", $challenges[1]->authScheme);
        $this->assertSame("second", $challenges[1]->parameter("realm"));
        $this->assertNull($challenges[1]->parameter("nonce"));
    }

    public function testQuotedCommasAndEmptyValuesArePreserved(): void
    {
        $challenges = Challenge::challengesFromAuthenticateFieldValue("Basic realm=\"sales, west\", charset=\"UTF-8\", empty=\"\"");
        $this->assertSame(1, $challenges->count);
        $this->assertSame("sales, west", $challenges[0]->parameter("REALM"));
        $this->assertSame("UTF-8", $challenges[0]->parameter("charset"));
        $this->assertSame("", $challenges[0]->parameter("empty"));
    }

    public function testUnterminatedQuotedParameterIsRejected(): void
    {
        $this->assertTrue(Challenge::challengesFromAuthenticateFieldValue("Basic realm=\"unfinished")->isEmpty);
    }
}
