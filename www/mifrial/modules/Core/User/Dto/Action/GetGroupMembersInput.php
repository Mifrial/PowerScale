<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Плоский JSON `userGroup.getMembers`.
 */
final class GetGroupMembersInput implements IActionInput
{
    /**
     * Собирает вход страницы членов.
     *
     * @param int $groupId Группа.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     *
     * @return void
     */
    public function __construct(
        public readonly int $groupId,
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }
}
