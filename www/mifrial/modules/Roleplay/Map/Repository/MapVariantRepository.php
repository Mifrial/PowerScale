<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException as SmartMapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Map\Dto\MapVariantRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNotFoundException;

/**
 * Строки `map_variant`: эпохи и подложки карты.
 */
final class MapVariantRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $variantRecords Строки вариантов.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $variantRecords,
    ) {
    }

    /**
     * Добавляет вариант.
     *
     * @param int $mapId Карта.
     * @param string $label Подпись.
     * @param int|null $fromMinutes Смещение.
     * @param int $sortOrder Порядок.
     * @param int|null $baseFileId Файл подложки.
     * @param int|null $width Ширина.
     * @param int|null $height Высота.
     * @param int|null $millimetersPerPixel Масштаб.
     * @param bool $isDefault Вариант по умолчанию.
     *
     * @return int Id.
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    public function add(
        int $mapId,
        string $label,
        ?int $fromMinutes,
        int $sortOrder,
        ?int $baseFileId,
        ?int $width,
        ?int $height,
        ?int $millimetersPerPixel,
        bool $isDefault,
    ): int {
        return $this->insert([
            'map_id' => $mapId,
            'label' => $label,
            'from_minutes' => $fromMinutes,
            'sort_order' => $sortOrder,
            'base_file_id' => $baseFileId,
            'width' => $width,
            'height' => $height,
            'millimeters_per_pixel' => $millimetersPerPixel,
            'is_default' => $isDefault,
        ]);
    }

    /**
     * Вариант по id.
     *
     * @param int $variantId Id.
     *
     * @return MapVariantRecord Вариант.
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если строка битая.
     */
    public function getById(int $variantId): MapVariantRecord
    {
        $row = $this->variantRecords->getById($variantId);
        if ($row === null) {
            throw new MapNotFoundException('Map variant was not found');
        }

        return MapVariantRecord::fromNormalized($row);
    }

    /**
     * Вариант в границах карты.
     *
     * @param int $mapId Карта.
     * @param int $variantId Вариант.
     *
     * @return MapVariantRecord Вариант.
     *
     * @throws MapNotFoundException Если варианта нет или он чужой.
     * @throws MapInvalidException Если строка битая.
     */
    public function getForMap(int $mapId, int $variantId): MapVariantRecord
    {
        $row = $this->variantRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['LOGIC' => 'AND', ['=id' => $variantId], ['=map_id' => $mapId]],
            'limit' => 1,
        ]));
        if ($row === null) {
            throw new MapNotFoundException('Map variant was not found');
        }

        return MapVariantRecord::fromNormalized($row);
    }

    /**
     * Варианты карты.
     *
     * @param int $mapId Карта.
     *
     * @return array<int, MapVariantRecord> Варианты.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByMap(int $mapId): array
    {
        $listResult = $this->listResult($mapId);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapVariantRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Пишет поля варианта.
     *
     * @param int $variantId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если поле недопустимо.
     */
    public function update(int $variantId, array $fields): void
    {
        try {
            $this->variantRecords->update($variantId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map variant was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException | UniqueConstraintException $exception) {
            throw new MapInvalidException('Map variant field is invalid', $exception);
        }
    }

    /**
     * Удаляет вариант.
     *
     * @param int $variantId Id.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     */
    public function delete(int $variantId): void
    {
        try {
            $this->variantRecords->delete($variantId);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map variant was not found', $exception);
        }
    }

    /**
     * Снимает default у остальных и ставит выбранному.
     *
     * @param int $mapId Карта.
     * @param int $variantId Вариант.
     *
     * @return void
     *
     * @throws MapInvalidException Если выборка или запись недопустимы.
     */
    public function setDefault(int $mapId, int $variantId): void
    {
        foreach ($this->findByMap($mapId) as $variantRecord) {
            $isDefault = $variantRecord->getId() === $variantId;
            if ($variantRecord->isDefault() === $isDefault) {
                continue;
            }

            $this->update($variantRecord->getId(), ['is_default' => $isDefault]);
        }
    }

    /**
     * Вставляет строку варианта.
     *
     * @param array<string, mixed> $fields Поля.
     *
     * @return int Id.
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    private function insert(array $fields): int
    {
        try {
            return $this->variantRecords->add($fields);
        } catch (
            FieldInvalidException
            | SmartMapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new MapInvalidException('Map variant row is invalid', $exception);
        }
    }

    /**
     * Читает варианты карты.
     *
     * @param int $mapId Карта.
     *
     * @return ListResult Страница.
     *
     * @throws MapInvalidException Если запрос недопустим.
     */
    private function listResult(int $mapId): ListResult
    {
        try {
            return $this->variantRecords->getList(ListQuery::fromOptions([
                'filter' => ['=map_id' => $mapId],
                'sort' => ['sort_order' => 'asc', 'id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
                'countTotal' => true,
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map variant list is invalid', $exception);
        }
    }
}
