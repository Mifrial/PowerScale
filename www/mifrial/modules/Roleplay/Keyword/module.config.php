<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Keyword\Action\CreateKeywordAction;
use Mifrial\Roleplay\Keyword\Action\DeactivateKeywordAction;
use Mifrial\Roleplay\Keyword\Action\GetKeywordAction;
use Mifrial\Roleplay\Keyword\Action\GetKeywordListAction;
use Mifrial\Roleplay\Keyword\Action\UpdateKeywordAction;
use Mifrial\Roleplay\Keyword\Container\KeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Keyword\Service\KeywordHttpService;
use Mifrial\Roleplay\Keyword\Service\KeywordPortFactory;
use Mifrial\Roleplay\Keyword\Setup\KeywordModuleSetup;

return [
    'container' => KeywordContainer::class,
    'locator' => IKeywordContainer::class,
    'setup' => KeywordModuleSetup::class,
    'ports' => [
        IKeywords::class => static function (IServiceLocator $serviceLocator): IKeywords {
            return (new KeywordPortFactory())->create($serviceLocator);
        },
        KeywordHttpService::class => static function (IServiceLocator $serviceLocator): KeywordHttpService {
            return (new KeywordPortFactory())->createHttp($serviceLocator);
        },
        GetKeywordListAction::class => static function (IServiceLocator $serviceLocator): GetKeywordListAction {
            return new GetKeywordListAction((new KeywordPortFactory())->fromContainer($serviceLocator));
        },
        GetKeywordAction::class => static function (IServiceLocator $serviceLocator): GetKeywordAction {
            return new GetKeywordAction((new KeywordPortFactory())->fromContainer($serviceLocator));
        },
        CreateKeywordAction::class => static function (IServiceLocator $serviceLocator): CreateKeywordAction {
            return new CreateKeywordAction((new KeywordPortFactory())->fromContainer($serviceLocator));
        },
        UpdateKeywordAction::class => static function (IServiceLocator $serviceLocator): UpdateKeywordAction {
            return new UpdateKeywordAction((new KeywordPortFactory())->fromContainer($serviceLocator));
        },
        DeactivateKeywordAction::class => static function (IServiceLocator $serviceLocator): DeactivateKeywordAction {
            return new DeactivateKeywordAction((new KeywordPortFactory())->fromContainer($serviceLocator));
        },
    ],
    'routes' => [
        'keyword.getList' => [
            'handler' => GetKeywordListAction::class,
            'csrf' => true,
        ],
        'keyword.get' => [
            'handler' => GetKeywordAction::class,
            'csrf' => true,
        ],
        'keyword.create' => [
            'handler' => CreateKeywordAction::class,
            'csrf' => true,
        ],
        'keyword.update' => [
            'handler' => UpdateKeywordAction::class,
            'csrf' => true,
        ],
        'keyword.deactivate' => [
            'handler' => DeactivateKeywordAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
