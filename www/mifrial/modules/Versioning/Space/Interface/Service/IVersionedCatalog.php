<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Interface\Service;

use Mifrial\Versioning\Space\Dto\ClusterSpec;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;

/**
 * Открывает репозиторий по реестру или явной спецификации кластера.
 */
interface IVersionedCatalog
{
    /**
     * Открывает кластер зарегистрированной сущности.
     *
     * @param string $identityClass Класс identity-таблицы.
     *
     * @return IVersionedRepository Репозиторий.
     *
     * @throws SpaceInvalidException Если класса нет в реестре.
     */
    public function open(string $identityClass): IVersionedRepository;

    /**
     * Открывает кластер по пяти картам, без записи в реестре.
     *
     * @param ClusterSpec $clusterSpec Кластер.
     *
     * @return IVersionedRepository Репозиторий.
     *
     * @throws SpaceInvalidException Если identity не карта ST.
     */
    public function openCluster(ClusterSpec $clusterSpec): IVersionedRepository;
}
