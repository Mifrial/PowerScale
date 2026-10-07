<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Action\ApplyCharacterActualPatchAction;
use Mifrial\Roleplay\Character\Action\CreateCharacterAction;
use Mifrial\Roleplay\Character\Action\GetCharacterAction;
use Mifrial\Roleplay\Character\Action\GetCharacterListAction;
use Mifrial\Roleplay\Character\Action\MigrateCharacterAction;
use Mifrial\Roleplay\Character\Action\UpdateCharacterAction;
use Mifrial\Roleplay\Character\Action\UpdateCharacterOwnerNotesAction;
use Mifrial\Roleplay\Character\Action\UpdateCharacterVisibilityAction;
use Mifrial\Roleplay\Character\Action\ValidateCharacterAction;
use Mifrial\Roleplay\Character\Container\CharacterContainer;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterCombatLayers;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterOsSteps;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSessionParticipants;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheetEngines;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\CharacterCombatLayerPortFactory;
use Mifrial\Roleplay\Character\Service\CharacterFormulaContextPortFactory;
use Mifrial\Roleplay\Character\Service\CharacterOsStepsPortFactory;
use Mifrial\Roleplay\Character\Service\CharacterPortFactory;
use Mifrial\Roleplay\Character\Service\CharacterRuleSlicePortFactory;
use Mifrial\Roleplay\Character\Service\CharacterSheetPortFactory;
use Mifrial\Roleplay\Character\Setup\CharacterModuleSetup;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;

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
        ICharacterFormulaContexts::class => static function (
            IServiceLocator $serviceLocator,
        ): ICharacterFormulaContexts {
            return (new CharacterFormulaContextPortFactory())->create($serviceLocator);
        },
        ICharacterCombatLayers::class => static function (
            IServiceLocator $serviceLocator,
        ): ICharacterCombatLayers {
            return (new CharacterCombatLayerPortFactory())->create($serviceLocator);
        },
        ICharacterSheets::class => static function (IServiceLocator $serviceLocator): ICharacterSheets {
            return (new CharacterSheetPortFactory())->create($serviceLocator);
        },
        ICharacterSheetEngines::class => static function (IServiceLocator $serviceLocator): ICharacterSheetEngines {
            return (new CharacterPortFactory())->createSheetEngine($serviceLocator);
        },
        ICharacterActualMutations::class => static function (
            IServiceLocator $serviceLocator,
        ): ICharacterActualMutations {
            return (new CharacterPortFactory())->createActualMutation($serviceLocator);
        },
        ApplyCharacterActualPatchAction::class => static function (
            IServiceLocator $serviceLocator,
        ): ApplyCharacterActualPatchAction {
            $patch = (new CharacterPortFactory())->createActualPatch($serviceLocator);

            return new ApplyCharacterActualPatchAction($patch);
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
            $sessionParticipants = $serviceLocator->get(IGameContainer::class)->get(ICharacterSessionParticipants::class);
            if (!$sessionParticipants instanceof ICharacterSessionParticipants) {
                throw new KernelException('PORT_TYPE', 'Character migrate requires session participants');
            }

            return new MigrateCharacterAction(
                (new CharacterPortFactory())->createMigration($serviceLocator, $sessionParticipants),
            );
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
        'character.applyActualPatch' => [
            'handler' => ApplyCharacterActualPatchAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
