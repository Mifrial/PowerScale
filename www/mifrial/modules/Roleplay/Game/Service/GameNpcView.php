<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameNpcRecord;

/**
 * JSON строки NPC.
 */
final class GameNpcView
{
    /**
     * Собирает вид.
     *
     * @param GameNpcVisibility $visibility Вырезание секций.
     *
     * @return void
     */
    public function __construct(
        private readonly GameNpcVisibility $visibility,
    ) {
    }

    /**
     * Карточка. fullSheet оставляет лист целиком.
     *
     * @param GameNpcRecord $record Строка.
     * @param bool $fullSheet Ведущий.
     *
     * @return array<string, mixed> JSON.
     */
    public function detail(GameNpcRecord $record, bool $fullSheet): array
    {
        return [
            'npcId' => $record->getId(),
            'gameId' => $record->getGameId(),
            'name' => $record->getName(),
            'version' => $this->visibility->mask($record->getVersion(), $record->getVisibility(), $fullSheet),
            'actualVersion' => $record->getActualVersion(),
            'visibility' => $record->getVisibility(),
        ];
    }
}
