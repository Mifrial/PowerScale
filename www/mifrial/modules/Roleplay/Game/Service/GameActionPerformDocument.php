<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Авторитетный лист актора для сценария действия.
 */
final class GameActionPerformDocument
{
    /**
     * Создаёт читатель листа.
     *
     * @param GameActionPerformPorts $ports Порты сценария.
     *
     * @return void
     */
    public function __construct(private readonly GameActionPerformPorts $ports)
    {
    }

    /**
     * Авторитетный лист актора.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     *
     * @return array{
     *     actualVersion: int,
     *     rulesRevision: int,
     *     choices: array<string, mixed>,
     *     sheet: array<string, mixed>
     * } Документ.
     *
     * @throws GameInvalidException Если лист NPC битый.
     * @throws GameNotFoundException Если актора нет или он чужой.
     */
    public function of(GameRecord $game, string $actorKind, int $actorId): array
    {
        if ($actorKind === 'character') {
            return $this->characterDocument($actorId);
        }

        return $this->npcDocument($game, $actorId);
    }

    /**
     * Документ персонажа.
     *
     * @param int $actorId Персонаж.
     *
     * @return array{
     *     actualVersion: int,
     *     rulesRevision: int,
     *     choices: array<string, mixed>,
     *     sheet: array<string, mixed>
     * } Документ.
     */
    private function characterDocument(int $actorId): array
    {
        $snapshot = $this->ports->rules->data->characterSnapshot($actorId);

        return [
            'actualVersion' => $snapshot['actualVersion'],
            'rulesRevision' => $snapshot['rulesRevision'],
            'choices' => $snapshot['choices'],
            'sheet' => $snapshot['sheet'],
        ];
    }

    /**
     * Документ NPC игры.
     *
     * @param GameRecord $game Игра.
     * @param int $actorId NPC.
     *
     * @return array{
     *     actualVersion: int,
     *     rulesRevision: int,
     *     choices: array<string, mixed>,
     *     sheet: array<string, mixed>
     * } Документ.
     *
     * @throws GameInvalidException Если лист NPC битый.
     * @throws GameNotFoundException Если NPC чужой.
     */
    private function npcDocument(GameRecord $game, int $actorId): array
    {
        $npc = $this->ports->npcs->getById($actorId);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game action npc was not found');
        }

        $version = $npc->getVersion();
        $choices = $version['choices'] ?? [];
        $sheet = $version['sheet'] ?? [];
        $revision = $version['rulesRevision'] ?? null;
        if (!is_array($choices) || !is_array($sheet) || !is_int($revision)) {
            throw new GameInvalidException('Game action npc sheet is invalid');
        }

        return [
            'actualVersion' => $npc->getActualVersion(),
            'rulesRevision' => $revision,
            'choices' => $choices,
            'sheet' => $sheet,
        ];
    }
}
