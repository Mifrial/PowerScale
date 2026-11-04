<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Плоский JSON `user.setAvatar`.
 */
final class SetUserAvatarInput implements IActionInput
{
    /**
     * Собирает вход setAvatar.
     *
     * @param string|null $filePublicId Загруженный файл или null, чтобы снять.
     *
     * @return void
     */
    public function __construct(
        public readonly ?string $filePublicId,
    ) {
    }
}
