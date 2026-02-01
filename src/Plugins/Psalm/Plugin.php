<?php

namespace Sabatier\Foundation\Plugins\Psalm;

use Override;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use Sabatier\Foundation\Plugins\Psalm\Hooks\CompactMapReturnTypeProvider;
use Sabatier\Foundation\Plugins\Psalm\Hooks\CovariantCollectionIssueHandler;
use Sabatier\Foundation\Plugins\Psalm\Hooks\JoinedReturnTypeProvider;
use SimpleXMLElement;

final class Plugin implements PluginEntryPointInterface
{
    /** @var list<class-string> */
    private const array hooks = [
        CompactMapReturnTypeProvider::class,
        CovariantCollectionIssueHandler::class,
        JoinedReturnTypeProvider::class,
    ];

    #[Override]
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        foreach (self::hooks as $hookClass) {
            if (class_exists($hookClass)) {
                $registration->registerHooksFromClass($hookClass);
            }
        }
    }
}
