<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Lore\Action\ArchiveLoreArticleAction;
use Mifrial\Roleplay\Lore\Action\ArchiveLoreWorldAction;
use Mifrial\Roleplay\Lore\Action\FindLoreArticlePageAction;
use Mifrial\Roleplay\Lore\Action\FindLoreReaderCandidatesAction;
use Mifrial\Roleplay\Lore\Action\ExportLoreWorldAction;
use Mifrial\Roleplay\Lore\Action\FindLoreWorldPageAction;
use Mifrial\Roleplay\Lore\Action\GetLoreArticleAction;
use Mifrial\Roleplay\Lore\Action\GetLoreWorldAction;
use Mifrial\Roleplay\Lore\Action\SaveLoreArticleAction;
use Mifrial\Roleplay\Lore\Action\ImportLoreWorldAction;
use Mifrial\Roleplay\Lore\Action\SaveLoreTreeAction;
use Mifrial\Roleplay\Lore\Action\SaveLoreWorldAction;
use Mifrial\Roleplay\Lore\Container\LoreContainer;
use Mifrial\Roleplay\Lore\Interface\Container\ILoreContainer;
use Mifrial\Roleplay\Lore\Service\LoreArticleHttpService;
use Mifrial\Roleplay\Lore\Service\LorePortFactory;
use Mifrial\Roleplay\Lore\Service\LoreTransferHttpService;
use Mifrial\Roleplay\Lore\Service\LoreWorldHttpService;
use Mifrial\Roleplay\Lore\Setup\LoreModuleSetup;

return [
    'container' => LoreContainer::class,
    'locator' => ILoreContainer::class,
    'setup' => LoreModuleSetup::class,
    'ports' => [
        LoreTransferHttpService::class => static function (IServiceLocator $serviceLocator): LoreTransferHttpService {
            return (new LorePortFactory())->createTransferHttp($serviceLocator);
        },
        LoreWorldHttpService::class => static function (IServiceLocator $serviceLocator): LoreWorldHttpService {
            return (new LorePortFactory())->createWorldHttp($serviceLocator);
        },
        LoreArticleHttpService::class => static function (IServiceLocator $serviceLocator): LoreArticleHttpService {
            return (new LorePortFactory())->createArticleHttp($serviceLocator);
        },
        FindLoreWorldPageAction::class => static function (IServiceLocator $serviceLocator): FindLoreWorldPageAction {
            return new FindLoreWorldPageAction((new LorePortFactory())->fromWorldContainer($serviceLocator));
        },
        GetLoreWorldAction::class => static function (IServiceLocator $serviceLocator): GetLoreWorldAction {
            return new GetLoreWorldAction((new LorePortFactory())->fromWorldContainer($serviceLocator));
        },
        SaveLoreWorldAction::class => static function (IServiceLocator $serviceLocator): SaveLoreWorldAction {
            return new SaveLoreWorldAction((new LorePortFactory())->fromWorldContainer($serviceLocator));
        },
        ArchiveLoreWorldAction::class => static function (IServiceLocator $serviceLocator): ArchiveLoreWorldAction {
            return new ArchiveLoreWorldAction((new LorePortFactory())->fromWorldContainer($serviceLocator));
        },
        SaveLoreTreeAction::class => static function (IServiceLocator $serviceLocator): SaveLoreTreeAction {
            return new SaveLoreTreeAction((new LorePortFactory())->fromWorldContainer($serviceLocator));
        },
        FindLoreArticlePageAction::class => static function (
            IServiceLocator $serviceLocator
        ): FindLoreArticlePageAction {
            return new FindLoreArticlePageAction((new LorePortFactory())->fromArticleContainer($serviceLocator));
        },
        GetLoreArticleAction::class => static function (IServiceLocator $serviceLocator): GetLoreArticleAction {
            return new GetLoreArticleAction((new LorePortFactory())->fromArticleContainer($serviceLocator));
        },
        SaveLoreArticleAction::class => static function (IServiceLocator $serviceLocator): SaveLoreArticleAction {
            return new SaveLoreArticleAction((new LorePortFactory())->fromArticleContainer($serviceLocator));
        },
        ArchiveLoreArticleAction::class => static function (IServiceLocator $serviceLocator): ArchiveLoreArticleAction {
            return new ArchiveLoreArticleAction((new LorePortFactory())->fromArticleContainer($serviceLocator));
        },
        ExportLoreWorldAction::class => static function (IServiceLocator $serviceLocator): ExportLoreWorldAction {
            return new ExportLoreWorldAction((new LorePortFactory())->fromTransferContainer($serviceLocator));
        },
        ImportLoreWorldAction::class => static function (IServiceLocator $serviceLocator): ImportLoreWorldAction {
            return new ImportLoreWorldAction((new LorePortFactory())->fromTransferContainer($serviceLocator));
        },
        FindLoreReaderCandidatesAction::class => static function (
            IServiceLocator $serviceLocator
        ): FindLoreReaderCandidatesAction {
            return new FindLoreReaderCandidatesAction((new LorePortFactory())->fromArticleContainer($serviceLocator));
        },
    ],
    'routes' => [
        'lore.findWorldPage' => ['handler' => FindLoreWorldPageAction::class, 'csrf' => true],
        'lore.getWorld' => ['handler' => GetLoreWorldAction::class, 'csrf' => true],
        'lore.saveWorld' => ['handler' => SaveLoreWorldAction::class, 'csrf' => true],
        'lore.archiveWorld' => ['handler' => ArchiveLoreWorldAction::class, 'csrf' => true],
        'lore.saveTree' => ['handler' => SaveLoreTreeAction::class, 'csrf' => true],
        'lore.findArticlePage' => ['handler' => FindLoreArticlePageAction::class, 'csrf' => true],
        'lore.getArticle' => ['handler' => GetLoreArticleAction::class, 'csrf' => true],
        'lore.saveArticle' => ['handler' => SaveLoreArticleAction::class, 'csrf' => true],
        'lore.archiveArticle' => ['handler' => ArchiveLoreArticleAction::class, 'csrf' => true],
        'lore.findReaderCandidates' => ['handler' => FindLoreReaderCandidatesAction::class, 'csrf' => true],
        'lore.exportWorld' => ['handler' => ExportLoreWorldAction::class, 'csrf' => true],
        'lore.importWorld' => ['handler' => ImportLoreWorldAction::class, 'csrf' => true],
    ],
    'events' => [],
];
