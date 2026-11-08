<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Снятие зажимов и слотов долга уходящего персонажа.
 */
final class GameCharacterRelease
{
    /**
     * Создаёт снятие.
     *
     * @param GameMembershipPorts $ports Хранилища строки.
     * @param IGames $games Игра.
     * @param ICharacters $sheets Actual листов.
     *
     * @return void
     */
    public function __construct(
        private readonly GameMembershipPorts $ports,
        private readonly IGames $games,
        private readonly ICharacters $sheets,
    ) {
    }

    /**
     * Снимает зажимы на листах и слоты долга в боях.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return void
     *
     * @throws GameNotFoundException Если листа нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function release(int $gameId, int $characterId): void
    {
        $this->releaseHolds($gameId, $characterId);
        $this->releaseDebt($gameId, $characterId);
    }

    /**
     * Снимает зажим уходящего персонажа на листах этой игры.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return void
     *
     * @throws GameNotFoundException Если листа нет.
     * @throws GameInvalidException Если строка битая.
     */
    private function releaseHolds(int $gameId, int $characterId): void
    {
        $holder = ['kind' => 'character', 'id' => $characterId];
        $game = $this->games->get($gameId);
        $npcs = new GameNpcRepository($this->ports->gateway);
        $writes = new GameStrikeSheetWrites($this->ports->mutations, $npcs);
        foreach ($this->ports->characters->getListByGame($gameId, null) as $membership) {
            $actual = $this->sheets->get($membership->getCharacterId());
            $patches = $this->holdPatches($actual->getSheet(), $holder);
            if ($patches === []) {
                continue;
            }

            $this->ports->mutations->apply($membership->getCharacterId(), $actual->getActualVersion(), $patches);
        }

        foreach ($npcs->getListByGame($gameId) as $npc) {
            $sheet = $npc->getVersion()['sheet'] ?? [];
            $patches = is_array($sheet) ? $this->holdPatches($sheet, $holder) : [];
            if ($patches === []) {
                continue;
            }

            $writes->write($game, 'npc', $npc->getId(), $npc->getActualVersion(), $patches);
        }
    }

    /**
     * Снимает слот долга персонажа в боях открытой сессии.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return void
     */
    private function releaseDebt(int $gameId, int $characterId): void
    {
        $sessions = new GameSessionRepository($this->ports->gateway);
        $sessionId = $sessions->findSessionId($gameId);
        if ($sessionId === null) {
            return;
        }

        $battles = new GameBattleRepository($this->ports->gateway);
        $key = 'character:' . $characterId;
        foreach ($battles->findIdsBySession($sessionId) as $battleId) {
            $battle = $battles->find($battleId);
            if ($battle === null || !array_key_exists($key, $battle->getActionDebt())) {
                continue;
            }

            $debt = $battle->getActionDebt();
            unset($debt[$key]);
            $battles->replaceActionDebt($battleId, $debt);
        }
    }

    /**
     * Операции снятия heldBy.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param array{kind: string, id: int} $holder Держатель.
     *
     * @return array<int, array<string, mixed>> Операции.
     */
    private function holdPatches(array $sheet, array $holder): array
    {
        $patches = [];
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            return [];
        }

        foreach ($states as $row) {
            if (!is_array($row) || ($row['heldBy'] ?? null) !== $holder || !is_int($row['id'] ?? null)) {
                continue;
            }

            $patches[] = ['kind' => 'patchState', 'id' => $row['id'], 'heldBy' => null];
        }

        return $patches;
    }
}
