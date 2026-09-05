<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Repository;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Пять открытых карт одного кластера.
 */
final class OpenedCluster
{
    /**
     * Собирает порты строк.
     *
     * @param IOpenedRecords $identities Identity.
     * @param IOpenedRecords $versions Экземпляры.
     * @param IOpenedRecords $spaces Пространства.
     * @param IOpenedRecords $revisions Ревизии.
     * @param IOpenedRecords $items Состав.
     * @param string $identityTableName Физическое имя identity.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $identities,
        private readonly IOpenedRecords $versions,
        private readonly IOpenedRecords $spaces,
        private readonly IOpenedRecords $revisions,
        private readonly IOpenedRecords $items,
        private readonly string $identityTableName,
    ) {
    }

    /**
     * Таблица identity.
     *
     * @return IOpenedRecords Строки.
     */
    public function getIdentities(): IOpenedRecords
    {
        return $this->identities;
    }

    /**
     * Экземпляры.
     *
     * @return IOpenedRecords Строки.
     */
    public function getVersions(): IOpenedRecords
    {
        return $this->versions;
    }

    /**
     * Пространства.
     *
     * @return IOpenedRecords Строки.
     */
    public function getSpaces(): IOpenedRecords
    {
        return $this->spaces;
    }

    /**
     * Ревизии.
     *
     * @return IOpenedRecords Строки.
     */
    public function getRevisions(): IOpenedRecords
    {
        return $this->revisions;
    }

    /**
     * Состав.
     *
     * @return IOpenedRecords Строки.
     */
    public function getItems(): IOpenedRecords
    {
        return $this->items;
    }

    /**
     * Физическое имя identity.
     *
     * @return string Имя таблицы.
     */
    public function getIdentityTableName(): string
    {
        return $this->identityTableName;
    }
}
