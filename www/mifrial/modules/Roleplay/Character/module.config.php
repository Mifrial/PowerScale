<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Action\CreateCharacterAction;
use Mifrial\Roleplay\Character\Action\GetCharacterAction;
use Mifrial\Roleplay\Character\Action\MigrateCharacterAction;
use Mifrial\Roleplay\Character\Action\GetCharacterListAction;
use Mifrial\Roleplay\Character\Action\UpdateCharacterAction;
use Mifrial\Roleplay\Character\Action\UpdateCharacterOwnerNotesAction;
use Mifrial\Roleplay\Character\Action\UpdateCharacterVisibilityAction;
use Mifrial\Roleplay\Character\Action\ValidateCharacterAction;
use Mifrial\Roleplay\Character\Container\CharacterContainer;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterOsSteps;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\CharacterOsStepsPortFactory;
use Mifrial\Roleplay\Character\Service\CharacterPortFactory;
use Mifrial\Roleplay\Character\Service\CharacterRuleSlicePortFactory;
use Mifrial\Roleplay\Character\Service\CharacterSheetPortFactory;
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
        ICharacterOsSteps::class => static function (IServiceLocator $serviceLocator): ICharacterOsSteps {
            return (new CharacterOsStepsPortFactory())->create($serviceLocator);
        },
        ICharacterSheets::class => static function (IServiceLocator $serviceLocator): ICharacterSheets {
            return (new CharacterSheetPortFactory())->create($serviceLocator);
        },
        CreateCharacterAction::class => static function (IServiceLocator $serviceLocator): CreateCharacterAction {
            return new CreateCharacterAction((new CharacterPortFactory())->createSave($serviceLocator));
        },
        UpdateCharacterAction::class => static function (IServiceLocator $serviceLocator): UpdateCharacterAction {
            return new UpdateCharacterAction((new CharacterPortFactory())->createSave($serviceLocator));
        },
        ValidateCharacterAction::class => static function (IServiceLocator $serviceLocator): ValidateCharacterAction {
            return new ValidateCharacterAction((new CharacterPortFactory())->createSave($serviceLocator));
        },
        MigrateCharacterAction::class => static function (IServiceLocator $serviceLocator): MigrateCharacterAction {
            return new MigrateCharacterAction((new CharacterPortFactory())->createMigration($serviceLocator));
        },
        GetCharacterListAction::class => static function (IServiceLocator $serviceLocator): GetCharacterListAction {
            return new GetCharacterListAction((new CharacterPortFactory())->createRead($serviceLocator));
        },
        GetCharacterAction::class => static function (IServiceLocator $serviceLocator): GetCharacterAction {
            return new GetCharacterAction((new CharacterPortFactory())->createRead($serviceLocator));
        },
        UpdateCharacterVisibilityAction::class => static function (IServiceLocator $serviceLocator): UpdateCharacterVisibilityAction {
            return new UpdateCharacterVisibilityAction((new CharacterPortFactory())->createRead($serviceLocator));
        },
        UpdateCharacterOwnerNotesAction::class => static function (IServiceLocator $serviceLocator): UpdateCharacterOwnerNotesAction {
            return new UpdateCharacterOwnerNotesAction((new CharacterPortFactory())->createRead($serviceLocator));
        },
    ],
    'routes' => [
        'character.create' => [
            'handler' => CreateCharacterAction::class,
            'csrf' => true,
        ],
        'character.update' => [
            'handler' => UpdateCharacterAction::class,
            'csrf' => true,
        ],
        'character.validate' => [
            'handler' => ValidateCharacterAction::class,
            'csrf' => true,
        ],
        'character.migrate' => [
            'handler' => MigrateCharacterAction::class,
            'csrf' => true,
        ],
        'character.getList' => [
            'handler' => GetCharacterListAction::class,
            'csrf' => true,
        ],
        'character.get' => [
            'handler' => GetCharacterAction::class,
            'csrf' => true,
        ],
        'character.updateVisibility' => [
            'handler' => UpdateCharacterVisibilityAction::class,
            'csrf' => true,
        ],
        'character.updateOwnerNotes' => [
            'handler' => UpdateCharacterOwnerNotesAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
