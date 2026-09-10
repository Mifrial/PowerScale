<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Container\CharacterContainer;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Service\CharacterPortFactory;
use Mifrial\Roleplay\Character\Service\CharacterRuleSlicePortFactory;
use Mifrial\Roleplay\Character\Setup\CharacterModuleSetup;

return [
    'container' => CharacterContainer::class,
    'locator' => ICharacterContainer::class,
    'setup' => CharacterModuleSetup::class,
    'ports' => [
        ICharacters::class => static function (IServiceLocator $serviceLocator): ICharacters {
            return (new CharacterPortFactory())->create($serviceLocator);
        },
        ICharacterRuleSlices::class => static function (IServiceLocator $serviceLocator): ICharacterRuleSlices {
            return (new CharacterRuleSlicePortFactory())->create($serviceLocator);
        },
    ],
    'routes' => [],
    'events' => [],
];
