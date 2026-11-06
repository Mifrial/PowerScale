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
use Mifrial\Roleplay\Map\Dto\MapLineRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNotFoundException;

/**
 * Строки `map_variant_line`: ленты рек и дорог.
 */
final class MapLineRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $lineRecords Строки лент.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $lineRecords,
    ) {
    }

    /**
     * Добавляет ленту.
     *
     * @param int $variantId Вариант.
     * @param string $color Цвет.
     * @param int $speed Проходимость.
     * @param array<int, array{0: int, 1: int}> $points Осевая линия.
     * @param array<int, int> $widths Ширина по точкам.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки Безье.
     * @param array<int, array{speed: int, color: string|null}> $segments Стили сегментов.
     * @param string $label Подпись.
     * @param bool $isPresent Присутствует.
     * @param int $sortOrder Порядок среди лент.
     *
     * @return int Id.
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    public function add(
        int $variantId,
        string $color,
        int $speed,
        array $points,
        array $widths,
        array $handles,
        array $segments,
        string $label,
        bool $isPresent,
        int $sortOrder,
    ): int {
        try {
            return $this->lineRecords->add([
                'variant_id' => $variantId,
                'color' => $color,
                'speed' => $speed,
                'points' => $points,
                'widths' => $widths,
                'handles' => $handles,
                'segments' => $segments,
                'label' => $label,
                'is_present' => $isPresent,
                'sort_order' => $sortOrder,
            ]);
        } catch (FieldInvalidException | SmartMapInvalidException | RowWriteFailedException $exception) {
            throw new MapInvalidException('Map line row is invalid', $exception);
        }
    }

    /**
     * Ленты варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return array<int, MapLineRecord> Ленты.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByVariant(int $variantId): array
    {
        $listResult = $this->listResult(['=variant_id' => $variantId]);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapLineRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Пишет поля ленты.
     *
     * @param int $lineId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если поле недопустимо.
     */
    public function update(int $lineId, array $fields): void
    {
        try {
            $this->lineRecords->update($lineId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map line was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map line field is invalid', $exception);
        }
    }

    /**
     * Удаляет ленту.
     *
     * @param int $lineId Id.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     */
    public function delete(int $lineId): void
    {
        try {
            $this->lineRecords->delete($lineId);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map line was not found', $exception);
        }
    }

    /**
     * Читает ленты по фильтру.
     *
     * @param array<int|string, mixed> $filter Фильтр.
     *
     * @return ListResult Страница.
     *
     * @throws MapInvalidException Если запрос недопустим.
     */
    private function listResult(array $filter): ListResult
    {
        try {
            return $this->lineRecords->getList(ListQuery::fromOptions([
                'filter' => $filter,
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map line list is invalid', $exception);
        }
    }
}
