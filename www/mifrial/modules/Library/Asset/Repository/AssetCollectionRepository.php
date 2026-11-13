<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Library\Asset\Dto\AssetCollectionRecord;
use Mifrial\Library\Asset\Exception\AssetInvalidException;
use Mifrial\Library\Asset\Exception\AssetNotFoundException;
use Mifrial\Library\Asset\Service\AssetRowErrors;

/**
 * Строки `asset_collection`: назначения, объявленные модулями.
 */
final class AssetCollectionRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $collectionRecords Строки коллекций.
     * @param AssetRowErrors $errors Карта ошибок строк.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $collectionRecords,
        private readonly AssetRowErrors $errors,
    ) {
    }

    /**
     * Добавляет коллекцию.
     *
     * @param string $module Владелец назначения.
     * @param string $code Код внутри модуля.
     * @param string $title Подпись для UI.
     *
     * @return int Id.
     *
     * @throws AssetInvalidException Если строка или пара модуль+код недопустимы.
     */
    public function add(string $module, string $code, string $title): int
    {
        return $this->errors->insert(
            $this->collectionRecords,
            [
                'module' => $module,
                'code' => $code,
                'title' => $title,
                'is_active' => true,
            ],
            'Asset collection row is invalid',
        );
    }

    /**
     * Коллекция по паре модуль+код.
     *
     * @param string $module Владелец назначения.
     * @param string $code Код внутри модуля.
     *
     * @return AssetCollectionRecord|null Запись или null.
     *
     * @throws AssetInvalidException Если выборка недопустима.
     */
    public function findByModuleAndCode(string $module, string $code): ?AssetCollectionRecord
    {
        $listResult = $this->errors->list(
            $this->collectionRecords,
            ListQuery::fromOptions([
                'filter' => [
                    'LOGIC' => 'AND',
                    ['=module' => $module],
                    ['=code' => $code],
                ],
                'limit' => 1,
            ]),
            'Asset collection query is invalid',
        );
        $rows = $listResult->rows();
        $row = $rows[0] ?? null;

        return is_array($row) ? AssetCollectionRecord::fromNormalized($row) : null;
    }

    /**
     * Коллекция по id.
     *
     * @param int $collectionId Id.
     *
     * @return AssetCollectionRecord Запись.
     *
     * @throws AssetNotFoundException Если строки нет.
     * @throws AssetInvalidException Если строка битая.
     */
    public function getById(int $collectionId): AssetCollectionRecord
    {
        $row = $this->collectionRecords->getById($collectionId);
        if (!is_array($row)) {
            throw new AssetNotFoundException('Asset collection was not found');
        }

        return AssetCollectionRecord::fromNormalized($row);
    }

    /**
     * Активная коллекция для выдачи и создания.
     *
     * @param string $module Владелец назначения.
     * @param string $code Код внутри модуля.
     *
     * @return AssetCollectionRecord Запись.
     *
     * @throws AssetInvalidException Если коллекции нет или она выключена.
     */
    public function getActiveByModuleAndCode(string $module, string $code): AssetCollectionRecord
    {
        $collectionRecord = $this->findByModuleAndCode($module, $code);
        if (!$collectionRecord instanceof AssetCollectionRecord || !$collectionRecord->isActive()) {
            throw new AssetInvalidException('Asset collection is not available');
        }

        return $collectionRecord;
    }
}
