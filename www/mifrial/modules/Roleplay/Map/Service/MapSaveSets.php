<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Коллекции снимка карты: списки строк, множества id и ссылки.
 */
final class MapSaveSets
{
    /**
     * Список строк по ключу.
     *
     * @param array<string|int, mixed> $source Источник.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return array<int, array<string|int, mixed>> Строки.
     *
     * @throws MapInvalidException Если это не список объектов.
     */
    public function rows(array $source, string $key, string $path): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value)) {
            throw new MapInvalidException($path . ' is not a list');
        }

        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                throw new MapInvalidException($path . ' has a non-object item');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Множество id.
     *
     * @param array<int, array<string, mixed>> $rows Строки.
     *
     * @return array<int, true> Id → true.
     */
    public function idSet(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[$row['id']] = true;
        }

        return $ids;
    }

    /**
     * Масштабы вариантов.
     *
     * @param array<int, array<string, mixed>> $variants Варианты.
     *
     * @return array<int, int|null> Id → миллиметры на пиксель.
     */
    public function scales(array $variants): array
    {
        $scales = [];
        foreach ($variants as $variant) {
            $scales[$variant['id']] = $variant['millimetersPerPixel'];
        }

        return $scales;
    }

    /**
     * Проверяет уникальность id.
     *
     * @param array<int, array<string, mixed>> $rows Строки.
     * @param string $label Имя коллекции.
     *
     * @return void
     *
     * @throws MapInvalidException Если id повторяются.
     */
    public function assertUniqueIds(array $rows, string $label): void
    {
        $seen = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            if (isset($seen[$id])) {
                throw new MapInvalidException($label . ' have a duplicate id');
            }

            $seen[$id] = true;
        }
    }

    /**
     * Проверяет ссылку на вариант снимка.
     *
     * @param int $variantId Ссылка.
     * @param array<int, true> $variantIds Варианты снимка.
     * @param string $path Путь.
     *
     * @return void
     *
     * @throws MapInvalidException Если варианта нет в снимке.
     */
    public function assertVariantExists(int $variantId, array $variantIds, string $path): void
    {
        if (!isset($variantIds[$variantId])) {
            throw new MapInvalidException($path . ' is unknown');
        }
    }
}
