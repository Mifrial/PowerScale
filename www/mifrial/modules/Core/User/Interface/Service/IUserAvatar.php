<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Interface\Service;

/**
 * Аватар учётки: выбранный круг, источник и производная.
 */
interface IUserAvatar
{
    /**
     * Ставит или снимает аватар текущего актора.
     *
     * @param string|null $filePublicId Исходник или null, чтобы снять.
     * @param int|null $cropX Левый край кропа.
     * @param int|null $cropY Верхний край кропа.
     * @param int|null $cropSize Сторона квадрата кропа.
     *
     * @return array<string, mixed> JSON User после изменения.
     */
    public function setAvatar(?string $filePublicId, ?int $cropX, ?int $cropY, ?int $cropSize): array;

    /**
     * Текущий источник, кроп и производная актора.
     *
     * @return array{avatar: string|null, source: array<string, mixed>|null, crop: array<string, int>|null} Состояние.
     */
    public function getAvatarState(): array;
}
