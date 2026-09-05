<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Keyword\Repository\KeywordRepository;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;

/**
 * Сборка фасада и HTTP признаков из локатора.
 */
final class KeywordPortFactory
{
    /**
     * Создаёт фасад.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IKeywords Фасад.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function create(IServiceLocator $serviceLocator): IKeywords
    {
        return new Keywords(
            new KeywordRepository($this->smartTableGateway($serviceLocator)->open(KeywordTable::class)->records()),
        );
    }

    /**
     * Создаёт HTTP-сценарий.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return KeywordHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function createHttp(IServiceLocator $serviceLocator): KeywordHttpService
    {
        return new KeywordHttpService(
            $this->userAccess($serviceLocator),
            $this->keywords($serviceLocator),
            new KeywordViewAssembler(),
        );
    }

    /**
     * HTTP из контейнера Keyword.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return KeywordHttpService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function fromContainer(IServiceLocator $serviceLocator): KeywordHttpService
    {
        $keywordContainer = $serviceLocator->get(IKeywordContainer::class);
        $keywordHttpService = $keywordContainer->get(KeywordHttpService::class);
        if (!$keywordHttpService instanceof KeywordHttpService) {
            throw new KernelException('PORT_TYPE', 'Keyword HTTP service has a wrong type');
        }

        return $keywordHttpService;
    }

    /**
     * Фасад из контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IKeywords Фасад.
     *
     * @throws KernelException Если тип чужой.
     */
    private function keywords(IServiceLocator $serviceLocator): IKeywords
    {
        $keywords = $serviceLocator->get(IKeywordContainer::class)->get(IKeywords::class);
        if (!$keywords instanceof IKeywords) {
            throw new KernelException('PORT_TYPE', 'Keyword HTTP requires IKeywords');
        }

        return $keywords;
    }

    /**
     * Guard учёток.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Guard.
     *
     * @throws KernelException Если тип чужой.
     */
    private function userAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $userAccess = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Keyword HTTP requires IUserAccess');
        }

        return $userAccess;
    }

    /**
     * Шлюз ST.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $smartTableGateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Keyword requires ISmartTableGateway');
        }

        return $smartTableGateway;
    }
}
