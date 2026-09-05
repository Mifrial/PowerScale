<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceCatalogRepository;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceRepository;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogItemTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogSectionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceMetaTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceRevisionCatalogTable;

/**
 * Сборка фасада и HTTP миров из локатора.
 */
final class RuleSpacePortFactory
{
    /**
     * Создаёт фасад.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IRuleSpaces Фасад.
     *
     * @throws KernelException Если нет шлюза или IRules.
     */
    public function create(IServiceLocator $serviceLocator): IRuleSpaces
    {
        $binder = new RuleSpaceCatalogBinder($this->catalogRepository($serviceLocator));

        return new RuleSpaces(
            $this->rules($serviceLocator),
            $this->ruleSpaceRepository($serviceLocator),
            new RuleSpaceWorldWriter(
                $this->rules($serviceLocator),
                $this->ruleSpaceRepository($serviceLocator),
                $binder,
            ),
            $this->smartTableGateway($serviceLocator),
        );
    }

    /**
     * Создаёт HTTP-сценарий.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return RuleSpaceHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function createHttp(IServiceLocator $serviceLocator): RuleSpaceHttpService
    {
        return new RuleSpaceHttpService(
            $this->userAccess($serviceLocator),
            $this->ruleSpaces($serviceLocator),
            $this->ruleSpaceRepository($serviceLocator),
            new RuleSpaceViewAssembler(),
            new RuleSpaceCommitDraftMapper(),
            new RuleSpaceSlug(),
        );
    }

    /**
     * HTTP из контейнера RuleSpace.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return RuleSpaceHttpService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function fromContainer(IServiceLocator $serviceLocator): RuleSpaceHttpService
    {
        $ruleSpaceContainer = $serviceLocator->get(IRuleSpaceContainer::class);
        $ruleSpaceHttpService = $ruleSpaceContainer->get(RuleSpaceHttpService::class);
        if (!$ruleSpaceHttpService instanceof RuleSpaceHttpService) {
            throw new KernelException('PORT_TYPE', 'RuleSpace HTTP service has a wrong type');
        }

        return $ruleSpaceHttpService;
    }

    /**
     * Фасад из контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IRuleSpaces Оператор.
     *
     * @throws KernelException Если тип чужой.
     */
    private function ruleSpaces(IServiceLocator $serviceLocator): IRuleSpaces
    {
        $ruleSpaces = $serviceLocator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
        if (!$ruleSpaces instanceof IRuleSpaces) {
            throw new KernelException('PORT_TYPE', 'RuleSpace HTTP requires IRuleSpaces');
        }

        return $ruleSpaces;
    }

    /**
     * Часы правила.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IRules Фасад.
     *
     * @throws KernelException Если тип чужой.
     */
    private function rules(IServiceLocator $serviceLocator): IRules
    {
        $rules = $serviceLocator->get(IRuleContainer::class)->get(IRules::class);
        if (!$rules instanceof IRules) {
            throw new KernelException('PORT_TYPE', 'RuleSpace requires IRules');
        }

        return $rules;
    }

    /**
     * Отдаёт guard учёток.
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
            throw new KernelException('PORT_TYPE', 'RuleSpace HTTP requires IUserAccess');
        }

        return $userAccess;
    }

    /**
     * Строки каталога.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return RuleSpaceCatalogRepository Репозиторий.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function catalogRepository(IServiceLocator $serviceLocator): RuleSpaceCatalogRepository
    {
        $gateway = $this->smartTableGateway($serviceLocator);

        return new RuleSpaceCatalogRepository(
            $gateway->open(RuleSpaceCatalogSectionTable::class)->records(),
            $gateway->open(RuleSpaceCatalogItemTable::class)->records(),
            $gateway->open(RuleSpaceRevisionCatalogTable::class)->records(),
        );
    }

    /**
     * Репозиторий мета мира.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return RuleSpaceRepository Репозиторий.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function ruleSpaceRepository(IServiceLocator $serviceLocator): RuleSpaceRepository
    {
        return new RuleSpaceRepository(
            $this->smartTableGateway($serviceLocator)->open(RuleSpaceMetaTable::class)->records(),
        );
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
            throw new KernelException('PORT_TYPE', 'RuleSpace requires ISmartTableGateway');
        }

        return $smartTableGateway;
    }
}
