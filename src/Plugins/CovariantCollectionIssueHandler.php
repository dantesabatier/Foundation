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

final class CovariantCollectionIssueHandler implements BeforeAddIssueInterface
{
    private static function getCovariantClasses(): array
    {
        return [ArrayClass::class, Set::class, Dictionary::class];
    }

    #[Override]
    public static function beforeAddIssue(BeforeAddIssueEvent $event): ?bool
    {
        $issue = $event->getIssue();
        if (!($issue instanceof InvalidArgument) && !($issue instanceof InvalidPropertyAssignmentValue)) {
            return null;
        }
        $message = $issue->message;
        if (array_any(self::getCovariantClasses(), fn($class) => str_contains($message, $class))) {
            return false;
        }
        return null;
    }
}
