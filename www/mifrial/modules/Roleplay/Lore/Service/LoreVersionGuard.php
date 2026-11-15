<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;
use Mifrial\Roleplay\Lore\Exception\LoreVersionConflictException;

/**
 * Проверка ожидаемой версии мира или статьи.
 */
final class LoreVersionGuard
{
    /**
     * Требует версию при правке.
     *
     * @param int|null $expectedVersion Версия клиента.
     * @param string $field Имя поля для ошибки.
     *
     * @return int Версия.
     *
     * @throws LoreInvalidException Если версии нет.
     */
    public function requireExpected(?int $expectedVersion, string $field): int
    {
        if ($expectedVersion === null) {
            throw new LoreInvalidException('Lore ' . $field . ' is required');
        }

        return $expectedVersion;
    }

    /**
     * Сверяет версию и бросает конфликт.
     *
     * @param int $expectedVersion Версия клиента.
     * @param int $currentVersion Версия строки.
     * @param int $updatedAt Время сохранения.
     * @param int|null $updatedBy Автор сохранения.
     *
     * @return void
     *
     * @throws LoreVersionConflictException Если версии разошлись.
     */
    public function assert(int $expectedVersion, int $currentVersion, int $updatedAt, ?int $updatedBy): void
    {
        if ($expectedVersion === $currentVersion) {
            return;
        }

        throw new LoreVersionConflictException($currentVersion, $updatedAt, $updatedBy);
    }
}
