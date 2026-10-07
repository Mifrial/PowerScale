<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Exception\CharacterSaveRejectedException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Пишет одну операцию удара в лист персонажа или NPC.
 */
final class GameStrikeSheetWrites
{
    /**
     * Создаёт запись.
     *
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param GameNpcRepository $npcs NPC.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterActualMutations $mutations,
        private readonly GameNpcRepository $npcs,
    ) {
    }

    /**
     * Персонаж через apply, NPC через документ лежащей строки.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Character или npc.
     * @param int $id Цель.
     * @param int $expected Версия листа.
     * @param array{kind: string, remainder: array{base: int, size: int}, quotient: int} $operation Операция.
     *
     * @return int Версия после записи.
     *
     * @throws GameBattleConflictException Если версия листа другая.
     * @throws GameInvalidException Если операция, документ или validate отвергнуты.
     * @throws GameNotFoundException Если строки или ревизии нет.
     */
    public function write(GameRecord $game, string $kind, int $id, int $expected, array $operation): int
    {
        try {
            return $kind === 'character'
                ? $this->mutations->apply($id, $expected, [$operation])->getActualVersion()
                : $this->writeNpc($game, $id, $expected, $operation);
        } catch (CharacterConflictException $exception) {
            throw new GameBattleConflictException(
                $exception->getCurrentVersion(),
                'Game strike sheet version conflict',
                $exception,
            );
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game strike sheet is invalid', $exception);
        } catch (CharacterSaveRejectedException $exception) {
            throw new GameInvalidException('Game strike sheet is invalid', $exception);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike sheet was not found', $exception);
        }
    }

    /**
     * NPC через applyToDocument и версию уже лежащей строки.
     *
     * @param GameRecord $game Игра.
     * @param int $npcId NPC.
     * @param int $expected Версия.
     * @param array{kind: string, remainder: array{base: int, size: int}, quotient: int} $operation Операция.
     *
     * @return int Версия.
     *
     * @throws GameInvalidException Если документ без ревизии.
     * @throws GameNotFoundException Если строки нет.
     * @throws CharacterInvalidException Если операция отвергнута.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    private function writeNpc(GameRecord $game, int $npcId, int $expected, array $operation): int
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game NPC was not found');
        }

        $document = $npc->getVersion();
        $choices = $document['choices'] ?? null;
        $sheet = $document['sheet'] ?? null;
        $spaceId = $document['spaceId'] ?? null;
        $revision = $document['rulesRevision'] ?? null;
        if (!is_array($choices) || !is_array($sheet) || !is_int($spaceId) || !is_int($revision)) {
            throw new GameInvalidException('Game strike sheet is invalid');
        }

        $patched = $this->mutations->applyToDocument($spaceId, $revision, $choices, $sheet, [$operation]);
        $document['choices'] = $patched['choices'];
        $document['sheet'] = $patched['sheet'];

        return $this->npcs->replaceVersion($npcId, $document, $expected)->getActualVersion();
    }
}
