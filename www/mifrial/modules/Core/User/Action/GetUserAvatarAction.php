<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Interface\Service\IUserAvatar;

/**
 * Текущее состояние аватара учётки.
 */
final class GetUserAvatarAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param IUserAvatar $userAvatar Аватар.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAvatar $userAvatar,
    ) {
    }

    /**
     * Возвращает источник, кроп и производную.
     *
     * @return array<string, mixed> Состояние.
     */
    public function handle(): array
    {
        return $this->userAvatar->avatarState();
    }
}
