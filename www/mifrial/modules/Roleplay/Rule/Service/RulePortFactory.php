<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
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
        $catalog = $serviceLocator->get(ISpaceContainer::class)->get(IVersionedCatalog::class);
        if (!$catalog instanceof IVersionedCatalog) {
            throw new KernelException('PORT_TYPE', 'Rule requires IVersionedCatalog');
        }

        $smartTableGateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Rule requires ISmartTableGateway');
        }

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
        );
    }
}
