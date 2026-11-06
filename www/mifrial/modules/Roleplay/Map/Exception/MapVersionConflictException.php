<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Exception;

use Throwable;

/**
 * Карту изменили с момента загрузки черновика.
 */
final class MapVersionConflictException extends MapException
{
    /**
     * Создаёт ошибку версии.
     *
     * @param int $currentVersion Текущая версия.
     * @param int $updatedAt Время публикации.
     * @param int|null $updatedBy Автор публикации.
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
        parent::__construct('MAP_VERSION_CONFLICT', 'Map was changed by another user', $previous);
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
