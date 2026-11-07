<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException as SmartMapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Map\Dto\MapPage;
use Mifrial\Roleplay\Map\Dto\MapPageQuery;
use Mifrial\Roleplay\Map\Dto\MapRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNotFoundException;

/**
 * Строки `map`: каталог, доступ и версия.
 */
final class MapRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $mapRecords Строки карт.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $mapRecords,
    ) {
    }

    /**
     * Добавляет карту.
     *
     * @param int $ownerId Владелец.
     * @param string $name Имя.
     * @param string $description Описание.
     * @param string $visibility Видимость public|private.
     * @param int $fillOpacity Плотность заливки объектов, 0–100.
     *
     * @return int Id.
     *
     * @throws MapInvalidException Если поля или карта недопустимы.
     */
    public function add(int $ownerId, string $name, string $description, string $visibility, int $fillOpacity): int
    {
        try {
            return $this->mapRecords->add([
                'owner_id' => $ownerId,
                'name' => $name,
                'description' => $description,
                'visibility' => $visibility,
                'fill_opacity' => $fillOpacity,
                'is_archived' => false,
                'version' => 1,
            ]);
        } catch (FieldInvalidException | SmartMapInvalidException | RowWriteFailedException $exception) {
            throw new MapInvalidException('Map row is invalid', $exception);
        }
    }

    /**
     * Карта по id.
     *
     * @param int $mapId Id.
     *
     * @return MapRecord Карта.
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если строка битая.
     */
    public function getById(int $mapId): MapRecord
    {
        $row = $this->mapRecords->getById($mapId);
        if ($row === null) {
            throw new MapNotFoundException();
        }

        return MapRecord::fromNormalized($row);
    }

    /**
     * Страница видимых карт.
     *
     * @param MapPageQuery $pageQuery Запрос.
     *
     * @return MapPage Страница.
     *
     * @throws MapInvalidException Если фильтр или выборка недопустимы.
     */
    public function findPage(MapPageQuery $pageQuery): MapPage
    {
        $listResult = $this->listResult($pageQuery);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapRecord::fromNormalized($row);
        }

        return new MapPage($records, $listResult->total() ?? 0);
    }

    /**
     * Пишет поля карты.
     *
     * @param int $mapId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если поле недопустимо.
     */
    public function update(int $mapId, array $fields): void
    {
        try {
            $this->mapRecords->update($mapId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map field is invalid', $exception);
        }
    }

    /**
     * Собирает ListQuery.
     *
     * @param MapPageQuery $pageQuery Запрос.
     *
     * @return ListResult Страница.
     *
     * @throws MapInvalidException Если фильтр недопустим.
     */
    private function listResult(MapPageQuery $pageQuery): ListResult
    {
        try {
            return $this->mapRecords->getList(ListQuery::fromOptions([
                'filter' => $this->pageFilter($pageQuery),
                'sort' => ['updated_at' => 'desc', 'id' => 'desc'],
                'limit' => $pageQuery->getLimit(),
                'offset' => $pageQuery->getOffset(),
                'countTotal' => true,
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map page query is invalid', $exception);
        }
    }

    /**
     * Фильтр страницы карт.
     *
     * @param MapPageQuery $pageQuery Запрос.
     *
     * @return array<int|string, mixed> Фильтр.
     */
    private function pageFilter(MapPageQuery $pageQuery): array
    {
        $filter = ['LOGIC' => 'AND', ['=is_archived' => $pageQuery->isArchived()]];
        if (!$pageQuery->canViewAll()) {
            $filter[] = [
                'LOGIC' => 'OR',
                ['=visibility' => 'public'],
                ['=owner_id' => $pageQuery->getViewerId()],
            ];
        }

        $ownerId = $pageQuery->getOwnerId();
        if ($ownerId !== null) {
            $filter[] = ['=owner_id' => $ownerId];
        }

        $searchQuery = $pageQuery->getSearchQuery();
        if ($searchQuery !== null) {
            $filter[] = ['%name' => '%' . $searchQuery . '%'];
        }

        return $filter;
    }
}
