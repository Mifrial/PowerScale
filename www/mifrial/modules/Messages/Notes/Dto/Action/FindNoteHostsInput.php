<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Сущности с заметками для левой колонки `/notes`.
 */
final class FindNoteHostsInput implements IActionInput
{
    /**
     * Собирает вход findHosts.
     *
     * @param string|null $q Подстрока.
     * @param string|null $hostType Тип сущности.
     * @param int|null $authorId Автор.
     * @param string $state Состояние: active, archived, all.
     * @param int $limit Число групп.
     *
     * @return void
     */
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $hostType = null,
        public readonly ?int $authorId = null,
        public readonly string $state = 'active',
        public readonly int $limit = 200,
    ) {
    }
}
