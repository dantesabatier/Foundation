<?php

namespace Sabatier\Foundation\Plugins;

use Override;
use Psalm\Issue\InvalidArgument;
use Psalm\Issue\InvalidPropertyAssignmentValue;
use Psalm\Plugin\EventHandler\BeforeAddIssueInterface;
use Psalm\Plugin\EventHandler\Event\BeforeAddIssueEvent;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\Slice;

final class CovariantCollectionIssueHandler implements BeforeAddIssueInterface
{
    private const array covariantClases = [
        ArrayClass::class,
        Dictionary::class,
        Set::class,
        Slice::class,
    ];

    #[Override]
    public static function beforeAddIssue(BeforeAddIssueEvent $event): ?bool
    {
        $issue = $event->getIssue();
        if (!($issue instanceof InvalidArgument) && !($issue instanceof InvalidPropertyAssignmentValue)) {
            return null;
        }
        $message = $issue->message;
        foreach (self::covariantClases as $class) {
            if (!str_contains($message, $class)) {
                continue;
            }
            $quotedClass = preg_quote($class, "/");
            $pattern = "/$quotedClass.*?(?:but|provided|assigned).*?$quotedClass/i";
            if (preg_match($pattern, $message)) {
                return false;
            }
        }
        return null;
    }
}
