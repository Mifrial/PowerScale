<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Права актора прохода экономики.
 */
final class GameEconomyActor
{
    /**
     * Создаёт проверку.
     *
     * @param GameEconomyPorts $ports Порты.
     * @param GameEconomyContext $context Контекст.
     *
     * @return void
     */
    public function __construct(
        private readonly GameEconomyPorts $ports,
        private readonly GameEconomyContext $context,
    ) {
    }

    /**
     * Владелец игры, gm или edit_all.
     *
     * @return bool Да, если может вести.
     */
    public function isStaff(): bool
    {
        if ($this->context->game->getOwnerId() === $this->context->actorUserId || $this->context->editAll) {
            return true;
        }

        try {
            return $this->ports->members->getByPair(
                $this->context->game->getId(),
                $this->context->actorUserId,
            )->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }

    /**
     * Ведущий.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    public function assertWriter(): void
    {
        if (!$this->isStaff()) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }
    }

    /**
     * Актор может менять этого персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если не active.
     * @throws GameNotFoundException Если строки нет.
     */
    public function assertCharacterActor(int $characterId): void
    {
        $row = $this->activeCharacter($characterId);
        if ($row->getCharacterOwnerId() === $this->context->actorUserId || $this->isStaff()) {
            return;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * Источник передачи.
     *
     * @param array{0: string, 1: int} $endpoint Конец.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если не active.
     * @throws GameNotFoundException Если нет.
     */
    public function assertEndpointActor(array $endpoint): void
    {
        if ($endpoint[0] === 'npc') {
            $this->assertWriter();
            $this->requireNpc($endpoint[1]);

            return;
        }

        $this->assertCharacterActor($endpoint[1]);
    }

    /**
     * Цель существует.
     *
     * @param array{0: string, 1: int} $endpoint Конец.
     *
     * @return void
     *
     * @throws GameInvalidException Если персонаж не active.
     * @throws GameNotFoundException Если нет.
     */
    public function assertEndpointKnown(array $endpoint): void
    {
        if ($endpoint[0] === 'npc') {
            $this->requireNpc($endpoint[1]);

            return;
        }

        $this->activeCharacter($endpoint[1]);
    }

    /**
     * Active-строка персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameInvalidException Если не active.
     * @throws GameNotFoundException Если нет.
     */
    public function activeCharacter(int $characterId): GameCharacterRecord
    {
        $row = $this->ports->memberships->get($this->context->game->getId(), $characterId);
        if ($row->getStatus() !== 'active') {
            throw new GameInvalidException('Game character is not active');
        }

        return $row;
    }

    /**
     * NPC этой игры.
     *
     * @param int $npcId NPC.
     *
     * @return GameNpcRecord Строка.
     *
     * @throws GameNotFoundException Если чужой.
     */
    public function requireNpc(int $npcId): GameNpcRecord
    {
        $npc = $this->ports->npcs->getById($npcId);
        if ($npc->getGameId() !== $this->context->game->getId()) {
            throw new GameNotFoundException();
        }

        return $npc;
    }
}
