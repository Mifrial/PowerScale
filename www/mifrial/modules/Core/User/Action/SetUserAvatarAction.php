<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Dto\Action\SetUserAvatarInput;
use Mifrial\Core\User\Interface\Service\IUserAvatar;

/**
 * Установка или снятие аватара учётки.
 */
final class SetUserAvatarAction implements IActionHandler
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
     * Ставит или снимает аватар текущего актора.
     *
     * @param SetUserAvatarInput $input JSON setAvatar.
     *
     * @return array<string, mixed> JSON User.
     */
    public function handle(SetUserAvatarInput $input): array
    {
        return $this->userAvatar->setAvatar($input->filePublicId);
    }
}
