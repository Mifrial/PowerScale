<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Плоский JSON `user.setAvatar`: источник и квадрат кропа.
 */
final class SetUserAvatarInput implements IActionInput
{
    /**
     * Собирает вход setAvatar.
     *
     * @param string|null $filePublicId Исходник или null, чтобы снять.
     * @param int|null $cropX Левый край кропа.
     * @param int|null $cropY Верхний край кропа.
     * @param int|null $cropSize Сторона квадрата кропа.
     *
     * @return void
     */
    public function __construct(
        public readonly ?string $filePublicId,
        public readonly ?int $cropX = null,
        public readonly ?int $cropY = null,
        public readonly ?int $cropSize = null,
    ) {
    }
}
