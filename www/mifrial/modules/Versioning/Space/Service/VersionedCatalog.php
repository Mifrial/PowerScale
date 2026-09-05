<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Service;

use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Versioning\Space\Dto\ClusterSpec;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;
use Mifrial\Versioning\Space\Interface\Service\IVersionedCatalog;
use Mifrial\Versioning\Space\Interface\Service\IVersionedRepository;
use Mifrial\Versioning\Space\Repository\CommitComposition;
use Mifrial\Versioning\Space\Repository\OpenedCluster;
use Mifrial\Versioning\Space\Repository\RevisionLoader;
use Mifrial\Versioning\Space\Repository\RevisionPublisher;
use Mifrial\Versioning\Space\Repository\VersionedRepository;

/**
 * Реестр кластеров → репозиторий.
 */
final class VersionedCatalog implements IVersionedCatalog
{
    /**
     * Создаёт каталог.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     * @param ICacheStore $cacheStore Кэш срезов.
     * @param bool $debug Fail-soft кэша.
     * @param array<string, ClusterSpec> $clusterMap Identity class → кластер.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly ICacheStore $cacheStore,
        private readonly bool $debug,
        private readonly array $clusterMap,
    ) {
    }

    /**
     * Открывает кластер зарегистрированной сущности.
     *
     * @param string $identityClass Класс identity-таблицы.
     *
     * @return IVersionedRepository Репозиторий.
     *
     * @throws SpaceInvalidException Если класса нет в реестре.
     */
    public function open(string $identityClass): IVersionedRepository
    {
        $spaceGuard = new SpaceGuard();

        return $spaceGuard->run(function () use ($identityClass): IVersionedRepository {
            $clusterSpec = $this->clusterMap[$identityClass] ?? null;
            if (!$clusterSpec instanceof ClusterSpec) {
                throw new SpaceInvalidException('Unknown versioned identity');
            }

            return $this->makeRepository($clusterSpec);
        });
    }

    /**
     * Открывает кластер по пяти картам, без записи в реестре.
     *
     * @param ClusterSpec $clusterSpec Кластер.
     *
     * @return IVersionedRepository Репозиторий.
     *
     * @throws SpaceInvalidException Если identity не карта ST.
     */
    public function openCluster(ClusterSpec $clusterSpec): IVersionedRepository
    {
        $spaceGuard = new SpaceGuard();

        return $spaceGuard->run(fn (): IVersionedRepository => $this->makeRepository($clusterSpec));
    }

    /**
     * Собирает фасад кластера.
     *
     * @param ClusterSpec $clusterSpec Кластер.
     *
     * @return IVersionedRepository Репозиторий.
     *
     * @throws SpaceInvalidException Если identity не definition.
     */
    private function makeRepository(ClusterSpec $clusterSpec): IVersionedRepository
    {
        $openedCluster = $this->openPorts($clusterSpec);

        return new VersionedRepository(
            $this->smartTableGateway,
            $openedCluster,
            new CommitComposition($openedCluster),
            new RevisionPublisher($openedCluster),
            new RevisionLoader($openedCluster),
            new RevisionSliceStore(
                $this->cacheStore,
                $this->debug,
                $openedCluster->getIdentityTableName(),
                new RevisionSliceCodec(),
                fn (): bool => $this->smartTableGateway->isTransactionOpen(),
            ),
        );
    }

    /**
     * Открывает пять карт.
     *
     * @param ClusterSpec $clusterSpec Кластер.
     *
     * @return OpenedCluster Порты.
     *
     * @throws SpaceInvalidException Если identity не definition.
     */
    private function openPorts(ClusterSpec $clusterSpec): OpenedCluster
    {
        $identityClass = $clusterSpec->getIdentityClass();
        $identityDefinition = new $identityClass();
        if (!$identityDefinition instanceof SmartTableDefinition) {
            throw new SpaceInvalidException('Unknown versioned identity');
        }

        return new OpenedCluster(
            $this->smartTableGateway->open($identityClass)->records(),
            $this->smartTableGateway->open($clusterSpec->getVersionClass())->records(),
            $this->smartTableGateway->open($clusterSpec->getSpaceClass())->records(),
            $this->smartTableGateway->open($clusterSpec->getRevisionClass())->records(),
            $this->smartTableGateway->open($clusterSpec->getItemClass())->records(),
            $identityDefinition->getName(),
        );
    }
}
