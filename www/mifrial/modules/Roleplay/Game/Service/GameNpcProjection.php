<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * NPC игры: индекс, допуск и лист после маски.
 */
final class GameNpcProjection
{
    /**
     * Создаёт чтение.
     *
     * @param GameNpcRepository $npcs Строки NPC.
     * @param GameNpcVisibility $visibility Маска секций.
     * @param GameProjectionAccess $access Доступ читателя.
     *
     * @return void
     */
    public function __construct(
        private readonly GameNpcRepository $npcs,
        private readonly GameNpcVisibility $visibility,
        private readonly GameProjectionAccess $access,
    ) {
    }

    /**
     * NPC игры по id.
     *
     * @param int $gameId Игра.
     *
     * @return array<int, GameNpcRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function index(int $gameId): array
    {
        $records = [];
        foreach ($this->npcs->getListByGame($gameId) as $record) {
            $records[$record->getId()] = $record;
        }

        return $records;
    }

    /**
     * Scope пускает к NPC.
     *
     * @param GameRecord $game Игра.
     * @param GameNpcRecord|null $record Строка или null.
     * @param int $actorUserId Актор.
     *
     * @return bool true, если NPC виден.
     */
    public function admitted(GameRecord $game, ?GameNpcRecord $record, int $actorUserId): bool
    {
        return $record instanceof GameNpcRecord && $this->access->npcAdmitted($game, $record, $actorUserId);
    }

    /**
     * NPC, которых scope пускает.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     *
     * @return list<array<string, mixed>> Записи.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function briefs(GameRecord $game, int $actorUserId): array
    {
        $briefs = [];
        foreach ($this->index($game->getId()) as $record) {
            if ($this->admitted($game, $record, $actorUserId)) {
                $briefs[] = $this->brief($record);
            }
        }

        return $briefs;
    }

    /**
     * Краткая запись NPC.
     *
     * @param GameNpcRecord $record Строка.
     *
     * @return array<string, mixed> JSON.
     */
    public function brief(GameNpcRecord $record): array
    {
        return [
            'type' => 'npc',
            'id' => $record->getId(),
            'name' => $record->getName(),
            'actualVersion' => $record->getActualVersion(),
        ];
    }

    /**
     * Лист NPC после маски.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param array<int, GameNpcRecord> $npcs Строки NPC.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed>|null Лист.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function sheet(GameRecord $game, int $actorUserId, array $npcs, int $npcId): ?array
    {
        $record = $npcs[$npcId] ?? null;
        if (!$record instanceof GameNpcRecord || !$this->admitted($game, $record, $actorUserId)) {
            return null;
        }

        $version = $this->visibility->mask(
            $record->getVersion(),
            $record->getVisibility(),
            $this->access->leading($game, $actorUserId),
        );

        return $this->document($record, $version);
    }

    /**
     * Лист NPC с миром на корне version.
     *
     * @param GameNpcRecord $record Строка.
     * @param array<string, mixed> $version После маски.
     *
     * @return array<string, mixed> JSON.
     */
    private function document(GameNpcRecord $record, array $version): array
    {
        $document = [
            'type' => 'npc',
            'id' => $record->getId(),
            'name' => $record->getName(),
            'actualVersion' => $record->getActualVersion(),
            'spaceId' => $version['spaceId'] ?? null,
            'rulesRevision' => $version['rulesRevision'] ?? null,
            'choices' => is_array($version['choices'] ?? null) ? $version['choices'] : [],
            'sheet' => is_array($version['sheet'] ?? null) ? $version['sheet'] : [],
        ];
        if (is_string($version['spaceCode'] ?? null)) {
            $document['spaceCode'] = $version['spaceCode'];
        }

        return $document;
    }
}
