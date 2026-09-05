<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\RuleSpace\Action\CommitSpaceDraftAction;
use Mifrial\Roleplay\RuleSpace\Action\CreateSpaceAction;
use Mifrial\Roleplay\RuleSpace\Action\DeactivateSpaceAction;
use Mifrial\Roleplay\RuleSpace\Action\GetSpaceAction;
use Mifrial\Roleplay\RuleSpace\Action\GetSpaceByCodeAction;
use Mifrial\Roleplay\RuleSpace\Action\GetSpaceListAction;
use Mifrial\Roleplay\RuleSpace\Action\GetSpaceRevisionAction;
use Mifrial\Roleplay\RuleSpace\Action\GetSpaceRevisionsAction;
use Mifrial\Roleplay\RuleSpace\Action\UpdateSpaceAction;
use Mifrial\Roleplay\RuleSpace\Container\RuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceHttpService;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpacePortFactory;
use Mifrial\Roleplay\RuleSpace\Setup\RuleSpaceModuleSetup;

return [
    'container' => RuleSpaceContainer::class,
    'locator' => IRuleSpaceContainer::class,
    'setup' => RuleSpaceModuleSetup::class,
    'ports' => [
        IRuleSpaces::class => static function (IServiceLocator $serviceLocator): IRuleSpaces {
            return (new RuleSpacePortFactory())->create($serviceLocator);
        },
        RuleSpaceHttpService::class => static function (IServiceLocator $serviceLocator): RuleSpaceHttpService {
            return (new RuleSpacePortFactory())->createHttp($serviceLocator);
        },
        GetSpaceListAction::class => static function (IServiceLocator $serviceLocator): GetSpaceListAction {
            return new GetSpaceListAction((new RuleSpacePortFactory())->fromContainer($serviceLocator));
        },
        GetSpaceAction::class => static function (IServiceLocator $serviceLocator): GetSpaceAction {
            return new GetSpaceAction((new RuleSpacePortFactory())->fromContainer($serviceLocator));
        },
        GetSpaceByCodeAction::class => static function (IServiceLocator $serviceLocator): GetSpaceByCodeAction {
            return new GetSpaceByCodeAction((new RuleSpacePortFactory())->fromContainer($serviceLocator));
        },
        CreateSpaceAction::class => static function (IServiceLocator $serviceLocator): CreateSpaceAction {
            return new CreateSpaceAction((new RuleSpacePortFactory())->fromContainer($serviceLocator));
        },
        UpdateSpaceAction::class => static function (IServiceLocator $serviceLocator): UpdateSpaceAction {
            return new UpdateSpaceAction((new RuleSpacePortFactory())->fromContainer($serviceLocator));
        },
        DeactivateSpaceAction::class => static function (IServiceLocator $serviceLocator): DeactivateSpaceAction {
            return new DeactivateSpaceAction((new RuleSpacePortFactory())->fromContainer($serviceLocator));
        },
        GetSpaceRevisionsAction::class => static function (IServiceLocator $serviceLocator): GetSpaceRevisionsAction {
            return new GetSpaceRevisionsAction((new RuleSpacePortFactory())->fromContainer($serviceLocator));
        },
        GetSpaceRevisionAction::class => static function (IServiceLocator $serviceLocator): GetSpaceRevisionAction {
            return new GetSpaceRevisionAction((new RuleSpacePortFactory())->fromContainer($serviceLocator));
        },
        CommitSpaceDraftAction::class => static function (IServiceLocator $serviceLocator): CommitSpaceDraftAction {
            return new CommitSpaceDraftAction((new RuleSpacePortFactory())->fromContainer($serviceLocator));
        },
    ],
    'routes' => [
        'ruleSpace.getList' => [
            'handler' => GetSpaceListAction::class,
            'csrf' => true,
        ],
        'ruleSpace.get' => [
            'handler' => GetSpaceAction::class,
            'csrf' => true,
        ],
        'ruleSpace.getByCode' => [
            'handler' => GetSpaceByCodeAction::class,
            'csrf' => true,
        ],
        'ruleSpace.create' => [
            'handler' => CreateSpaceAction::class,
            'csrf' => true,
        ],
        'ruleSpace.update' => [
            'handler' => UpdateSpaceAction::class,
            'csrf' => true,
        ],
        'ruleSpace.deactivate' => [
            'handler' => DeactivateSpaceAction::class,
            'csrf' => true,
        ],
        'ruleSpace.getRevisions' => [
            'handler' => GetSpaceRevisionsAction::class,
            'csrf' => true,
        ],
        'ruleSpace.getRevision' => [
            'handler' => GetSpaceRevisionAction::class,
            'csrf' => true,
        ],
        'ruleSpace.commitDraft' => [
            'handler' => CommitSpaceDraftAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
