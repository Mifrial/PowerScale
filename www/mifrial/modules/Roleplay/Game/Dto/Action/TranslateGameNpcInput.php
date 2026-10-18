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
     * @param array<string, mixed>|null $choices Документ перевода, если лист уже собрал клиент.
     * @param string|null $name Имя из перевода.
     * @param array<string, mixed>|null $sheet Сверка листа.
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
        public readonly ?array $choices = null,
        public readonly ?string $name = null,
        public readonly ?array $sheet = null,
    ) {
    }
}
