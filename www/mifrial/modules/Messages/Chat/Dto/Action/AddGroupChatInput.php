<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Имя группы и дополнительные члены.
 */
final class AddGroupChatInput implements IActionInput
{
    /**
     * Собирает вход addGroup.
     *
     * @param string $name Имя.
     * @param array<int, int> $memberIds Дополнительные id; нет ключа → [].
     *
     * @return void
     */
    public function __construct(
        public readonly string $name,
        public readonly array $memberIds = [],
    ) {
    }
}
