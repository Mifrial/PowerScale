<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Страница заметок: фильтры и page.
 */
final class FindNotePageInput implements IActionInput
{
    /**
     * Собирает вход findPage.
     *
     * @param string|null $q Подстрока.
     * @param string|null $hostType Тип сущности.
     * @param int|null $hostId Id сущности.
     * @param int|null $authorId Автор.
     * @param bool|null $pinned Закрепление.
     * @param string $state Состояние: active, archived, all.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     *
     * @return void
     */
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $hostType = null,
        public readonly ?int $hostId = null,
        public readonly ?int $authorId = null,
        public readonly ?bool $pinned = null,
        public readonly string $state = 'active',
        public readonly int $limit = 50,
        public readonly int $offset = 0,
    ) {
    }
}
