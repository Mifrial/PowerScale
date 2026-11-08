<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Создание заметки на странице сущности.
 */
final class CreateNoteInput implements IActionInput
{
    /**
     * Собирает вход note.create.
     *
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     * @param string $title Заголовок.
     * @param string $bodyHtml HTML.
     *
     * @return void
     */
    public function __construct(
        public readonly string $hostType,
        public readonly int $hostId,
        public readonly string $title,
        public readonly string $bodyHtml = '',
    ) {
    }
}
