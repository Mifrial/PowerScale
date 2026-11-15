<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\Kernel\Interface\Service\ITransactionRunner;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\SmartTableTransactionRunner;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Lore\Interface\Container\ILoreContainer;
use Mifrial\Roleplay\Lore\Repository\LoreArticleRepository;
use Mifrial\Roleplay\Lore\Repository\LoreLinkRepository;
use Mifrial\Roleplay\Lore\Repository\LoreSectionRepository;
use Mifrial\Roleplay\Lore\Repository\LoreWorldRepository;
use Mifrial\Roleplay\Lore\Table\LoreArticleTable;
use Mifrial\Roleplay\Lore\Table\LoreLinkTable;
use Mifrial\Roleplay\Lore\Table\LoreSectionTable;
use Mifrial\Roleplay\Lore\Table\LoreWorldTable;

/**
 * Сборка сервисов Roleplay/Lore из локатора.
 */
final class LorePortFactory
{
    /**
     * HTTP мира.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreWorldHttpService Сценарий.
     */
    public function createWorldHttp(IServiceLocator $serviceLocator): LoreWorldHttpService
    {
        return new LoreWorldHttpService(
            $this->accessService($serviceLocator),
            $this->worlds($serviceLocator),
            $this->viewAssembler(),
        );
    }

    /**
     * HTTP статьи.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreArticleHttpService Сценарий.
     */
    public function createArticleHttp(IServiceLocator $serviceLocator): LoreArticleHttpService
    {
        return new LoreArticleHttpService(
            $this->accessService($serviceLocator),
            $this->articles($serviceLocator),
            $this->viewAssembler(),
        );
    }

    /**
     * HTTP переноса.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreTransferHttpService Сценарий.
     */
    public function createTransferHttp(IServiceLocator $serviceLocator): LoreTransferHttpService
    {
        return new LoreTransferHttpService(
            $this->accessService($serviceLocator),
            $this->exportService($serviceLocator),
            $this->importService($serviceLocator),
        );
    }

    /**
     * HTTP мира из контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreWorldHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function fromWorldContainer(IServiceLocator $serviceLocator): LoreWorldHttpService
    {
        $service = $serviceLocator->get(ILoreContainer::class)->get(LoreWorldHttpService::class);
        if (!$service instanceof LoreWorldHttpService) {
            throw new KernelException('PORT_TYPE', 'Lore world HTTP has a wrong type');
        }

        return $service;
    }

    /**
     * HTTP статьи из контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreArticleHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function fromArticleContainer(IServiceLocator $serviceLocator): LoreArticleHttpService
    {
        $service = $serviceLocator->get(ILoreContainer::class)->get(LoreArticleHttpService::class);
        if (!$service instanceof LoreArticleHttpService) {
            throw new KernelException('PORT_TYPE', 'Lore article HTTP has a wrong type');
        }

        return $service;
    }

    /**
     * HTTP переноса из контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreTransferHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function fromTransferContainer(IServiceLocator $serviceLocator): LoreTransferHttpService
    {
        $service = $serviceLocator->get(ILoreContainer::class)->get(LoreTransferHttpService::class);
        if (!$service instanceof LoreTransferHttpService) {
            throw new KernelException('PORT_TYPE', 'Lore transfer HTTP has a wrong type');
        }

        return $service;
    }

    /**
     * Экспорт.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreExportService Сервис.
     */
    private function exportService(IServiceLocator $serviceLocator): LoreExportService
    {
        return new LoreExportService(
            $this->accessService($serviceLocator),
            $this->sectionRepository($serviceLocator),
            $this->articleRepository($serviceLocator),
            $this->keywords($serviceLocator),
            $this->userAccounts($serviceLocator),
        );
    }

    /**
     * Импорт.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreImportService Сервис.
     */
    private function importService(IServiceLocator $serviceLocator): LoreImportService
    {
        return new LoreImportService(
            $this->accessService($serviceLocator),
            $this->worldRepository($serviceLocator),
            $this->sectionRepository($serviceLocator),
            $this->articleRepository($serviceLocator),
            $this->linkRepository($serviceLocator),
            $this->keywords($serviceLocator),
            $this->userAccounts($serviceLocator),
            new LoreImportValidator(
                new LoreWorldValidator(),
                new LoreTreeValidator(),
                new LoreArticleValidator(),
                new LoreImportHtml(),
            ),
            new LoreTreeValidator(),
            new LoreLinkExtractor(),
            $this->transactionRunner($serviceLocator),
        );
    }

    /**
     * Признаки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IKeywords Порт.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function keywords(IServiceLocator $serviceLocator): IKeywords
    {
        $keywords = $serviceLocator->get(IKeywordContainer::class)->get(IKeywords::class);
        if (!$keywords instanceof IKeywords) {
            throw new KernelException('PORT_TYPE', 'Lore requires IKeywords');
        }

        return $keywords;
    }

    /**
     * Доступ.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreAccessService Сервис.
     */
    private function accessService(IServiceLocator $serviceLocator): LoreAccessService
    {
        return new LoreAccessService(
            $this->userAccess($serviceLocator),
            $this->worldRepository($serviceLocator),
            $this->articleRepository($serviceLocator),
        );
    }

    /**
     * Сценарии мира.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreWorlds Сервис.
     */
    private function worlds(IServiceLocator $serviceLocator): LoreWorlds
    {
        return new LoreWorlds(
            $this->accessService($serviceLocator),
            $this->worldRepository($serviceLocator),
            $this->sectionRepository($serviceLocator),
            $this->articleRepository($serviceLocator),
            new LoreWorldValidator(),
            new LoreTreeValidator(),
            new LoreVersionGuard(),
            $this->transactionRunner($serviceLocator),
        );
    }

    /**
     * Сценарии статьи.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreArticles Сервис.
     */
    private function articles(IServiceLocator $serviceLocator): LoreArticles
    {
        return new LoreArticles(
            $this->accessService($serviceLocator),
            $this->sectionRepository($serviceLocator),
            $this->articleRepository($serviceLocator),
            $this->linkRepository($serviceLocator),
            new LoreArticleValidator(),
            new LoreLinkExtractor(),
            new LoreVersionGuard(),
            $this->transactionRunner($serviceLocator),
            $this->userAccounts($serviceLocator),
        );
    }

    /**
     * JSON-сборщик.
     *
     * @return LoreViewAssembler Сервис.
     */
    private function viewAssembler(): LoreViewAssembler
    {
        return new LoreViewAssembler();
    }

    /**
     * Миры.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreWorldRepository Строки.
     */
    private function worldRepository(IServiceLocator $serviceLocator): LoreWorldRepository
    {
        return new LoreWorldRepository(
            $this->smartTableGateway($serviceLocator)->open(LoreWorldTable::class)->records(),
            new LoreRowErrors(),
        );
    }

    /**
     * Разделы.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreSectionRepository Строки.
     */
    private function sectionRepository(IServiceLocator $serviceLocator): LoreSectionRepository
    {
        return new LoreSectionRepository(
            $this->smartTableGateway($serviceLocator)->open(LoreSectionTable::class)->records(),
            new LoreRowErrors(),
        );
    }

    /**
     * Статьи.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreArticleRepository Строки.
     */
    private function articleRepository(IServiceLocator $serviceLocator): LoreArticleRepository
    {
        return new LoreArticleRepository(
            $this->smartTableGateway($serviceLocator)->open(LoreArticleTable::class)->records(),
            new LoreRowErrors(),
        );
    }

    /**
     * Ссылки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoreLinkRepository Строки.
     */
    private function linkRepository(IServiceLocator $serviceLocator): LoreLinkRepository
    {
        return new LoreLinkRepository(
            $this->smartTableGateway($serviceLocator)->open(LoreLinkTable::class)->records(),
            new LoreRowErrors(),
        );
    }

    /**
     * Guard учёток.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Guard.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function userAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $userAccess = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Lore requires IUserAccess');
        }

        return $userAccess;
    }

    /**
     * Учётки читателей.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccounts Порт.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function userAccounts(IServiceLocator $serviceLocator): IUserAccounts
    {
        $userAccounts = $serviceLocator->get(IUserContainer::class)->get(IUserAccounts::class);
        if (!$userAccounts instanceof IUserAccounts) {
            throw new KernelException('PORT_TYPE', 'Lore requires IUserAccounts');
        }

        return $userAccounts;
    }

    /**
     * Единица работы.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ITransactionRunner Runner.
     */
    private function transactionRunner(IServiceLocator $serviceLocator): ITransactionRunner
    {
        return new SmartTableTransactionRunner($this->smartTableGateway($serviceLocator));
    }

    /**
     * Шлюз ST.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $gateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$gateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Lore requires ISmartTableGateway');
        }

        return $gateway;
    }
}
