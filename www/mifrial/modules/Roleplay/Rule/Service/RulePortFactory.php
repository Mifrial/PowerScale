<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\Rule\Repository\RuleClockMapper;
use Mifrial\Roleplay\Rule\Repository\RuleIdentityRepository;
use Mifrial\Roleplay\Rule\Table\RuleRevisionItemTable;
use Mifrial\Roleplay\Rule\Table\RuleRevisionTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use Mifrial\Roleplay\Rule\Table\RuleTable;
use Mifrial\Roleplay\Rule\Table\RuleVersionTable;
use Mifrial\Versioning\Space\Dto\ClusterSpec;
use Mifrial\Versioning\Space\Interface\Container\ISpaceContainer;
use Mifrial\Versioning\Space\Interface\Service\IVersionedCatalog;

/**
 * Сборка фасада правил из локатора.
 */
final class RulePortFactory
{
    /**
     * Создаёт фасад.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IRules Фасад.
     *
     * @throws KernelException Если нет шлюза или каталога часов.
     */
    public function create(IServiceLocator $serviceLocator): IRules
    {
        $catalog = $this->catalog($serviceLocator);
        $smartTableGateway = $this->smartTableGateway($serviceLocator);
        $mechanics = $this->mechanics($serviceLocator);

        $identityRepository = new RuleIdentityRepository(
            $smartTableGateway->open(RuleTable::class)->records(),
        );

        return new Rules(
            $catalog->openCluster(new ClusterSpec(
                RuleTable::class,
                RuleVersionTable::class,
                RuleSpaceTable::class,
                RuleRevisionTable::class,
                RuleRevisionItemTable::class,
            )),
            new RuleClockMapper($identityRepository),
            $mechanics,
        );
    }

    /**
     * Каталог версий из локатора.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IVersionedCatalog Каталог.
     *
     * @throws KernelException Если порт чужого типа.
     */
    private function catalog(IServiceLocator $serviceLocator): IVersionedCatalog
    {
        $catalog = $serviceLocator->get(ISpaceContainer::class)->get(IVersionedCatalog::class);
        if (!$catalog instanceof IVersionedCatalog) {
            throw new KernelException('PORT_TYPE', 'Rule requires IVersionedCatalog');
        }

        return $catalog;
    }

    /**
     * Шлюз SmartTable из локатора.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если порт чужого типа.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $gateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$gateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Rule requires ISmartTableGateway');
        }

        return $gateway;
    }

    /**
     * Механики из локатора.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IMechanics Механики.
     *
     * @throws KernelException Если порт чужого типа.
     */
    private function mechanics(IServiceLocator $serviceLocator): IMechanics
    {
        $mechanics = $serviceLocator->get(IMechanicContainer::class)->get(IMechanics::class);
        if (!$mechanics instanceof IMechanics) {
            throw new KernelException('PORT_TYPE', 'Rule requires IMechanics');
        }

        return $mechanics;
    }
}
