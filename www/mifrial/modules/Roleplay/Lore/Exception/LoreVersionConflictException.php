<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Exception;

use Throwable;

/**
 * Мир или статью изменили с момента загрузки черновика.
 */
final class LoreVersionConflictException extends LoreException
{
    /**
     * Создаёт ошибку версии.
     *
     * @param int $currentVersion Текущая версия.
     * @param int $updatedAt Время сохранения.
     * @param int|null $updatedBy Автор сохранения.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        private readonly int $currentVersion,
        private readonly int $updatedAt,
        private readonly ?int $updatedBy,
        ?Throwable $previous = null,
    ) {
        parent::__construct('LORE_VERSION_CONFLICT', 'Lore row was changed by another user', $previous);
    }

    /**
     * Поля конверта для диалога конфликта.
     *
     * @return array<string, mixed> Детали.
     */
    public function getErrorDetails(): array
    {
        return [
            'currentVersion' => $this->currentVersion,
            'updatedAt' => $this->updatedAt,
            'updatedBy' => $this->updatedBy,
        ];
    }
}
