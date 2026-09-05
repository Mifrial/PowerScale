<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalArray;

/**
 * Вход публикации черновика мира.
 */
final class CommitDraftInput implements IActionInput
{
    /**
     * Собирает вход публикации.
     *
     * @param int $spaceId Мир.
     * @param array<int, mixed> $rules Выбранные тела.
     * @param OptionalArray $sections Дерево или absent.
     * @param array<int, mixed> $removedCodes Tombstone.
     *
     * @return void
     */
    public function __construct(
        public readonly int $spaceId,
        public readonly array $rules,
        public readonly OptionalArray $sections,
        public readonly array $removedCodes = [],
    ) {
    }
}
