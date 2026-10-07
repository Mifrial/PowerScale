<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.updateChronicleEntry.
 */
final class UpdateGameChronicleEntryInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $entryId Запись.
     * @param string $title Заголовок.
     * @param string $content Текст.
     * @param array<mixed> $offset Шесть единиц.
     *
     * @return void
     */
    public function __construct(
        public readonly int $entryId,
        public readonly string $title,
        public readonly string $content,
        public readonly array $offset,
    ) {
    }
}
