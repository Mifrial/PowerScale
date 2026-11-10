<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;

/**
 * Общие операции над списками строк снимка локации.
 *
 * Отрицательный id — новая строка, положительный обязан существовать,
 * пропавшие строки удаляются.
 */
final class SceneSaveRows
{
    /**
     * Делит строки снимка на обновления и вставки.
     *
     * @param array<int, array<string|int, mixed>> $rows Строки снимка.
     * @param array<int, true> $existingById Известные строки по id.
     * @param string $unknown Текст ошибки для неизвестного id.
     *
     * @return array{0: array<int, array<string|int, mixed>>, 1: array<int, array<string|int, mixed>>} Части.
     *
     * @throws SceneInvalidException Если строка снимка неизвестна.
     */
    public function split(array $rows, array $existingById, string $unknown): array
    {
        $updates = [];
        $inserts = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if ($id < 0) {
                $inserts[] = $row;

                continue;
            }

            if (!isset($existingById[$id])) {
                throw new SceneInvalidException($unknown);
            }

            $updates[$id] = $row;
        }

        return [$updates, $inserts];
    }

    /**
     * Карта id снимка → реальный id для обновлённых строк.
     *
     * @param array<int, array<string|int, mixed>> $updates Обновляемые строки.
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
     * @param array<int, true> $existingById Известные строки по id.
     * @param array<int, array<string|int, mixed>> $updates Обновляемые строки.
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
