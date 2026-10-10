<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Exception\CharacterSaveRejectedException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
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
     * @param array{type: string, id: int}|null $target Цель wide-удара.
     * @param Closure(CharacterConflictException|GameEconomyConflictException): array{choices: array<string, mixed>, sheet: array<string, mixed>}|null $projectConflict Проектор entity-stale конфликта.
     * @param array<int, ResourceSpend> $spends Подготовленные resource spends.
     *
     * @return int Версия после записи.
     *
     * @throws GameBattleConflictException Если версия листа другая.
     * @throws GameInvalidException Если операция, документ или validate отвергнуты.
     * @throws GameNotFoundException Если строки или ревизии нет.
     */
    public function write(
        GameRecord $game,
        string $kind,
        int $id,
        int $expected,
        ?array $operation,
        ?array $target = null,
        ?Closure $projectConflict = null,
        array $spends = [],
    ): int {
        $operations = $this->operations($operation, $spends);
        try {
            return $kind === 'character'
                ? $this->mutations->apply($id, $expected, $operations)->getActualVersion()
                : $this->writeNpc($game, $id, $expected, $operations);
        } catch (CharacterConflictException | GameEconomyConflictException $exception) {
            $currentSheet = $exception->getCurrentSheet() === null || $projectConflict === null
                ? null
                : $projectConflict($exception);
            throw $this->battleConflict($exception, $currentSheet, $target);
        } catch (CharacterInvalidException | CharacterSaveRejectedException $exception) {
            throw new GameInvalidException('Game strike sheet is invalid', $exception);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike sheet was not found', $exception);
        }
    }

    /**
     * Списывает ресурсы без записи принятого эффекта.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Character или npc.
     * @param int $id Цель.
     * @param int $expected Версия.
     * @param array<int, ResourceSpend> $spends Подготовленные списания.
     * @param array{type: string, id: int}|null $target Цель wide-удара.
     * @param Closure(CharacterConflictException|GameEconomyConflictException): array{choices: array<string, mixed>, sheet: array<string, mixed>}|null $projectConflict Проектор конфликта.
     *
     * @return int Версия после записи.
     *
     * @throws GameBattleConflictException Если версия листа другая.
     * @throws GameInvalidException Если документ или validate отвергнуты.
     * @throws GameNotFoundException Если строка отсутствует.
     */
    public function writeResources(
        GameRecord $game,
        string $kind,
        int $id,
        int $expected,
        array $spends,
        ?array $target = null,
        ?Closure $projectConflict = null,
    ): int {
        return $this->write(
            $game,
            $kind,
            $id,
            $expected,
            null,
            $target,
            $projectConflict,
            $spends,
        );
    }

    /**
     * Переносит конфликт Character/Economy на combat boundary.
     *
     * @param CharacterConflictException|GameEconomyConflictException $exception Конфликт записи.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>}|null $currentSheet Проекция листа.
     * @param array{type: string, id: int}|null $target Цель wide-удара.
     *
     * @return GameBattleConflictException Combat-конфликт.
     */
    private function battleConflict(
        CharacterConflictException|GameEconomyConflictException $exception,
        ?array $currentSheet,
        ?array $target,
    ): GameBattleConflictException {
        return new GameBattleConflictException(
            $exception->getCurrentVersion(),
            'Game strike sheet version conflict',
            $exception,
            $currentSheet,
            $target,
        );
    }

    /**
     * NPC через applyToDocument и версию уже лежащей строки.
     *
     * @param GameRecord $game Игра.
     * @param int $npcId NPC.
     * @param int $expected Версия.
     * @param list<array<string, mixed>> $operations Операции.
     *
     * @return int Версия.
     *
     * @throws GameInvalidException Если документ без ревизии.
     * @throws GameNotFoundException Если строки нет.
     * @throws CharacterInvalidException Если операция отвергнута.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    private function writeNpc(GameRecord $game, int $npcId, int $expected, array $operations): int
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

        $patched = $this->mutations->applyToDocument($spaceId, $revision, $choices, $sheet, $operations);
        $document['choices'] = $patched['choices'];
        $document['sheet'] = $patched['sheet'];

        return $this->npcs->replaceVersion($npcId, $document, $expected)->getActualVersion();
    }

    /**
     * Объединяет effect operation и уже resolved spends.
     *
     * @param array<string, mixed> $operation Effect operation.
     * @param array<int, ResourceSpend> $spends Prepared spends.
     *
     * @return list<array<string, mixed>> Operations for mutation boundary.
     */
    private function operations(?array $operation, array $spends): array
    {
        $operations = [];
        if ($spends !== []) {
            $operations[] = ['kind' => 'spendResources', 'spends' => $spends];
        }

        if ($operation !== null) {
            $operations[] = $operation;
        }

        return $operations;
    }
}
