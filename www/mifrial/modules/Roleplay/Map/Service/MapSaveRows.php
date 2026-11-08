<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Общие операции над списками строк снимка карты.
 *
 * Правило снимка одно для узлов, вариантов, групп и областей: отрицательный id —
 * новая строка, положительный обязан существовать, а пропавшие строки удаляются.
 */
final class MapSaveRows
{
    /**
     * Делит строки снимка на обновления и вставки.
     *
     * @param array<int, array<string, mixed>> $rows Строки снимка.
     * @param array<int, mixed> $existingById Известные строки по id.
     * @param string $unknown Текст ошибки для неизвестного id.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} Части.
     *
     * @throws MapInvalidException Если строка снимка неизвестна.
     */
    public function split(array $rows, array $existingById, string $unknown): array
    {
        $updates = [];
        $inserts = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            if ($id < 0) {
                $inserts[] = $row;

                continue;
            }

            if (!isset($existingById[$id])) {
                throw new MapInvalidException($unknown);
            }

            $updates[$id] = $row;
        }

        return [$updates, $inserts];
    }

    /**
     * Карта id снимка → реальный id для обновлённых строк.
     *
     * @param array<int, array<string, mixed>> $updates Обновляемые строки.
     *
     * @return array<int, int> Id снимка → реальный id.
     */
    public function ids(array $updates): array
    {
        $idMap = [];
        foreach (array_keys($updates) as $id) {
            $idMap[$id] = $id;
        }

        return $idMap;
    }

    /**
     * Id существующих строк, которых нет в снимке.
     *
     * @param array<int, mixed> $existingById Известные строки по id.
     * @param array<int, array<string, mixed>> $updates Обновляемые строки.
     *
     * @return array<int, int> Id на удаление.
     */
    public function stale(array $existingById, array $updates): array
    {
        $stale = [];
        foreach (array_keys($existingById) as $id) {
            if (!isset($updates[$id])) {
                $stale[] = $id;
            }
        }

        return $stale;
    }
}
