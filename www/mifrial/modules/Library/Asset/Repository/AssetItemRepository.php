<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Library\Asset\Dto\AssetPage;
use Mifrial\Library\Asset\Dto\AssetPageQuery;
use Mifrial\Library\Asset\Dto\AssetRecord;
use Mifrial\Library\Asset\Enum\AssetScope;
use Mifrial\Library\Asset\Exception\AssetInvalidException;
use Mifrial\Library\Asset\Exception\AssetNotFoundException;
use Mifrial\Library\Asset\Service\AssetRowErrors;

/**
 * Строки `asset_item`: выдача, переименование и архив.
 */
final class AssetItemRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $itemRecords Строки ассетов.
     * @param AssetRowErrors $errors Карта ошибок строк.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $itemRecords,
        private readonly AssetRowErrors $errors,
    ) {
    }

    /**
     * Добавляет ассет.
     *
     * @param int $collectionId Назначение.
     * @param int $fileId Файл.
     * @param bool $isShared Общая часть.
     * @param int|null $ownerId Владелец личной части.
     * @param string $name Подпись.
     *
     * @return int Id.
     *
     * @throws AssetInvalidException Если строка недопустима.
     */
    public function add(
        int $collectionId,
        int $fileId,
        bool $isShared,
        ?int $ownerId,
        string $name,
    ): int {
        return $this->errors->insert(
            $this->itemRecords,
            [
                'collection_id' => $collectionId,
                'file_id' => $fileId,
                'is_shared' => $isShared,
                'owner_id' => $ownerId,
                'name' => $name,
                'is_archived' => false,
            ],
            'Asset row is invalid',
        );
    }

    /**
     * Ассет по id.
     *
     * @param int $assetId Id.
     *
     * @return AssetRecord Запись.
     *
     * @throws AssetNotFoundException Если строки нет.
     * @throws AssetInvalidException Если строка битая.
     */
    public function getById(int $assetId): AssetRecord
    {
        $row = $this->itemRecords->getById($assetId);
        if (!is_array($row)) {
            throw new AssetNotFoundException();
        }

        return AssetRecord::fromNormalized($row);
    }

    /**
     * Страница ассетов коллекции и части библиотеки.
     *
     * @param AssetPageQuery $pageQuery Запрос.
     *
     * @return AssetPage Страница.
     *
     * @throws AssetInvalidException Если фильтр или выборка недопустимы.
     */
    public function findPage(AssetPageQuery $pageQuery): AssetPage
    {
        $listResult = $this->errors->list(
            $this->itemRecords,
            ListQuery::fromOptions([
                'filter' => $this->pageFilter($pageQuery),
                'sort' => ['name' => 'asc', 'id' => 'asc'],
                'limit' => $pageQuery->getLimit(),
                'offset' => $pageQuery->getOffset(),
                'countTotal' => true,
            ]),
            'Asset page query is invalid',
        );
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = AssetRecord::fromNormalized($row);
        }

        return new AssetPage($records, $listResult->total() ?? 0);
    }

    /**
     * Пишет подпись ассета.
     *
     * @param int $assetId Id.
     * @param string $name Подпись.
     *
     * @return void
     *
     * @throws AssetNotFoundException Если строки нет.
     * @throws AssetInvalidException Если поле недопустимо.
     */
    public function updateName(int $assetId, string $name): void
    {
        $this->errors->updateById(
            $this->itemRecords,
            $assetId,
            ['name' => $name],
            'Asset name is invalid',
        );
    }

    /**
     * Ставит архивную отметку.
     *
     * @param int $assetId Id.
     *
     * @return void
     *
     * @throws AssetNotFoundException Если строки нет.
     * @throws AssetInvalidException Если поле недопустимо.
     */
    public function markArchived(int $assetId): void
    {
        $this->errors->updateById(
            $this->itemRecords,
            $assetId,
            ['is_archived' => true],
            'Asset archive flag is invalid',
        );
    }

    /**
     * Фильтр страницы ассетов.
     *
     * @param AssetPageQuery $pageQuery Запрос.
     *
     * @return array<int|string, mixed> Фильтр.
     */
    private function pageFilter(AssetPageQuery $pageQuery): array
    {
        $isShared = $pageQuery->getScope() === AssetScope::Shared;
        $filter = [
            'LOGIC' => 'AND',
            ['=collection_id' => $pageQuery->getCollectionId()],
            ['=is_shared' => $isShared],
            ['=is_archived' => false],
        ];
        if (!$isShared) {
            $filter[] = ['=owner_id' => $pageQuery->getViewerId()];
        }

        $searchQuery = $pageQuery->getSearchQuery();
        if ($searchQuery !== null) {
            $filter[] = ['%name' => '%' . $searchQuery . '%'];
        }

        return $filter;
    }
}
