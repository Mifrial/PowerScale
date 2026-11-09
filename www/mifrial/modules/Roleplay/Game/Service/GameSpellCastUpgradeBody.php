<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор списка улучшений каста.
 */
final class GameSpellCastUpgradeBody
{
    /**
     * Нормализует необязательный список улучшений.
     *
     * @param mixed $raw Значение из запроса.
     *
     * @return array<int, array{code: string, value: int|null}> Список.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (!is_array($raw) || !array_is_list($raw)) {
            throw $this->invalid();
        }

        $normalized = [];
        $seen = [];
        foreach ($raw as $row) {
            $item = $this->row($row);
            if (isset($seen[$item['code']])) {
                throw $this->invalid();
            }

            $seen[$item['code']] = true;
            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * Одна строка улучшения.
     *
     * @param mixed $row Строка.
     *
     * @return array{code: string, value: int|null} Строка.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function row(mixed $row): array
    {
        if (!is_array($row) || array_is_list($row)) {
            throw $this->invalid();
        }

        $keys = array_keys($row);
        sort($keys);
        if ($keys !== ['code'] && $keys !== ['code', 'value']) {
            throw $this->invalid();
        }

        $code = $row['code'] ?? null;
        if (!is_string($code) || $code === '') {
            throw $this->invalid();
        }

        $value = $row['value'] ?? null;
        if ($value !== null && (!is_int($value) || $value < 0)) {
            throw $this->invalid();
        }

        return ['code' => $code, 'value' => $value];
    }

    /**
     * Ошибка формы.
     *
     * @return ActionException Исключение.
     */
    private function invalid(): ActionException
    {
        return new ActionException('INVALID_PARAMS', 'Game spell upgrades are invalid');
    }
}
