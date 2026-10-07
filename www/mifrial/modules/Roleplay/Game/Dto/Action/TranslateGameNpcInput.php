<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.translateNpc.
 */
final class TranslateGameNpcInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     * @param int $expectedNpcActualVersion CAS.
     * @param int|null $spaceId Сверка мира.
     * @param string|null $spaceCode Сверка кода.
     * @param int|null $rulesRevision Сверка ревизии листа.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $npcId,
        public readonly int $expectedNpcActualVersion,
        public readonly ?int $spaceId = null,
        public readonly ?string $spaceCode = null,
        public readonly ?int $rulesRevision = null,
    ) {
    }
}
