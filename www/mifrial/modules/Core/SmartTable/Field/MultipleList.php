<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Field;

use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Cast и extract list для multiple-поля.
 */
trait MultipleList
{
    /**
     * Приводит list multiple.
     *
     * @param mixed $resolvedValue Вход после default.
     *
     * @return array<int, mixed> Элементы.
     *
     * @throws FieldInvalidException Если это не list.
     * @throws FieldRequiredException Если required и list пуст.
     */
    private function castMultiple(mixed $resolvedValue): array
    {
        $itemList = $resolvedValue === null ? [] : $resolvedValue;
        if (!is_array($itemList) || !array_is_list($itemList)) {
            throw new FieldInvalidException('Multiple value must be a list');
        }

        $castedItems = [];
        foreach ($itemList as $itemValue) {
            if ($itemValue === null) {
                throw new FieldInvalidException('Multiple list cannot contain null');
            }

            $castedItems[] = $this->castPresent($itemValue);
        }

        if ($castedItems === [] && $this->settings()->required()) {
            throw new FieldRequiredException();
        }

        return $castedItems;
    }

    /**
     * Готовит list multiple к БД.
     *
     * @param mixed $phpValue List после cast.
     *
     * @return array<int, mixed> Значения для mfv.
     *
     * @throws FieldInvalidException Если это не list.
     * @throws FieldRequiredException Если required и list пуст.
     * @throws MapInvalidException Если есть дубль.
     */
    private function extractMultiple(mixed $phpValue): array
    {
        if (!is_array($phpValue) || !array_is_list($phpValue)) {
            throw new FieldInvalidException('Multiple value must be a list');
        }

        if ($phpValue === [] && $this->settings()->required()) {
            throw new FieldRequiredException();
        }

        $extractedValues = [];
        foreach ($phpValue as $itemValue) {
            $extractedValues[] = $this->extractedUniqueItem($itemValue, $extractedValues);
        }

        return $extractedValues;
    }

    /**
     * Extract одного элемента без дубля в накопленном list.
     *
     * @param mixed $itemValue Элемент.
     * @param array<int, mixed> $extractedValues Уже собранные значения.
     *
     * @return mixed Значение для mfv.
     *
     * @throws FieldInvalidException Если элемент null.
     * @throws MapInvalidException Если значение уже есть.
     */
    private function extractedUniqueItem(mixed $itemValue, array $extractedValues): mixed
    {
        if ($itemValue === null) {
            throw new FieldInvalidException('Multiple list cannot contain null');
        }

        $extractedValue = $this->extractPresent($itemValue);
        if (in_array($extractedValue, $extractedValues, true)) {
            throw new MapInvalidException('Multiple list cannot contain duplicates');
        }

        return $extractedValue;
    }
}
