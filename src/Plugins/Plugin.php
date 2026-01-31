<?php

namespace Sabatier\Foundation\Plugins;

use Override;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;

final class Plugin implements PluginEntryPointInterface
{
    #[Override]
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        class_exists(CompactMapReturnTypeProvider::class);
        $registration->registerHooksFromClass(CompactMapReturnTypeProvider::class);
    }
}
