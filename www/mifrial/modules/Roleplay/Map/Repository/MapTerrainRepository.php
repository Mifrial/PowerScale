<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException as SmartMapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Map\Dto\MapTerrainRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNotFoundException;

/**
 * Строки `map_terrain`: глобальный справочник поверхностей.
 */
final class MapTerrainRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $terrainRecords Строки террейнов.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $terrainRecords,
    ) {
    }

    /**
     * Досевает отсутствующие коды.
     *
     * @param array<int, array<string, mixed>> $terrains Строки.
     *
     * @return void
     *
     * @throws MapInvalidException Если строка недопустима.
     */
    public function ensureDefaults(array $terrains): void
    {
        foreach ($terrains as $terrain) {
            if ($this->hasCode($terrain['code'])) {
                continue;
            }

            try {
                $this->terrainRecords->add($terrain);
            } catch (FieldInvalidException | SmartMapInvalidException | UniqueConstraintException $exception) {
                throw new MapInvalidException('Map terrain seed is invalid', $exception);
            }
        }
    }

    /**
     * Все террейны.
     *
     * @return array<int, MapTerrainRecord> Справочник.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findAll(): array
    {
        $listResult = $this->listResult();
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapTerrainRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Террейн по коду.
     *
     * @param string $code Код.
     *
     * @return MapTerrainRecord Террейн.
     *
     * @throws MapNotFoundException Если кода нет.
     * @throws MapInvalidException Если строка битая.
     */
    public function getByCode(string $code): MapTerrainRecord
    {
        $row = $this->terrainRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['=code' => $code],
            'limit' => 1,
        ]));
        if ($row === null) {
            throw new MapNotFoundException('Map terrain was not found');
        }

        return MapTerrainRecord::fromNormalized($row);
    }

    /**
     * Террейн по id.
     *
     * @param int $terrainId Id.
     *
     * @return MapTerrainRecord Террейн.
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если строка битая.
     */
    public function getById(int $terrainId): MapTerrainRecord
    {
        $row = $this->terrainRecords->getById($terrainId);
        if ($row === null) {
            throw new MapNotFoundException('Map terrain was not found');
        }

        return MapTerrainRecord::fromNormalized($row);
    }

    /**
     * Террейны по кодам.
     *
     * @param array<int, string> $codes Коды.
     *
     * @return array<string, MapTerrainRecord> Код → террейн.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByCodes(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        try {
            $listResult = $this->terrainRecords->getList(ListQuery::fromOptions([
                'filter' => ['=code' => array_values(array_unique($codes))],
                'limit' => count($codes),
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map terrain lookup is invalid', $exception);
        }

        $records = [];
        foreach ($listResult->rows() as $row) {
            $terrainRecord = MapTerrainRecord::fromNormalized($row);
            $records[$terrainRecord->getCode()] = $terrainRecord;
        }

        return $records;
    }

    /**
     * Есть ли код.
     *
     * @param string $code Код.
     *
     * @return bool Флаг.
     *
     * @throws MapInvalidException Если запрос недопустим.
     */
    private function hasCode(string $code): bool
    {
        try {
            $row = $this->terrainRecords->getUnique(ListQuery::fromOptions([
                'filter' => ['=code' => $code],
                'limit' => 1,
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map terrain lookup is invalid', $exception);
        }

        return $row !== null;
    }

    /**
     * Читает весь справочник.
     *
     * @return ListResult Страница.
     *
     * @throws MapInvalidException Если запрос недопустим.
     */
    private function listResult(): ListResult
    {
        try {
            return $this->terrainRecords->getList(ListQuery::fromOptions([
                'sort' => ['sort_order' => 'asc', 'id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map terrain list is invalid', $exception);
        }
    }
}
