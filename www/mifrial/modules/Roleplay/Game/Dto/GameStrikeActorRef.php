<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Участник удара: персонаж или NPC с его снимком листа.
 */
final class GameStrikeActorRef
{
    /**
     * Создаёт ссылку на участника.
     *
     * @param string $kind Character или npc.
     * @param int $id Идентификатор.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC или null.
     * @param array<string, mixed>|null $npcChoices Снимок choices NPC или null.
     * @param int|null $sheetVersion Версия листа NPC.
     * @param array<int, array<string, mixed>> $runtimeStates Состояния цели из наложенных эффектов.
     * @param int|null $spaceId Мир листа.
     * @param int|null $rulesRevision Ревизия листа.
     *
     * @return void
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $id,
        public readonly ?array $npcSheet,
        public readonly ?array $npcChoices,
        public readonly ?int $sheetVersion,
        public readonly array $runtimeStates = [],
        public readonly ?int $spaceId = null,
        public readonly ?int $rulesRevision = null,
    ) {
    }
}
