<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Бой и проекция конфликта команды действия.
 */
final class GameActionBattle
{
    /**
     * Создаёт сценарий.
     *
     * @param GameActionPorts $ports Порты команды.
     * @param GameActionValue $value Значения строк.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionPorts $ports,
        private readonly GameActionValue $value,
    ) {
    }

    /**
     * Проверяет доступ, бой, цель и отсутствие открытого действия.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param array<string, mixed> $input Вход.
     * @param bool $followUp Нужна ли цель прошлого удара.
     *
     * @return GameBattleRecord Бой.
     *
     * @throws \Mifrial\Core\Kernel\Exception\ActionException AUTH_DENIED.
     * @throws GameInvalidException Если действие уже открыто.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия боя другая.
     */
    public function battleFor(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        array $input,
        bool $followUp,
    ): GameBattleRecord {
        $this->ports->access->assertAttacker($game, $actorUserId, $editAll, $input['actor']);
        $sessionId = $this->ports->sessions->findSessionId($game->getId());
        $battle = $this->battleOf($sessionId, $input['battleId'], $input['expectedBattle']);
        if ($this->ports->actions->findOpenAny($input['battleId']) !== null) {
            throw new GameInvalidException('Game action is already open');
        }

        $targets = $followUp ? [$this->value->targetOfKey((string) $input['targetKey'])] : [];
        $this->ports->access->assertRoster($input['battleId'], $input['actor'], $targets);

        return $battle;
    }

    /**
     * Бой текущей сессии с проверкой версии.
     *
     * @param int|null $sessionId Сессия.
     * @param int $battleId Бой.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return GameBattleRecord Строка боя.
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия другая.
     */
    public function battleOf(?int $sessionId, int $battleId, int $expectedVersion): GameBattleRecord
    {
        $battle = $this->ports->battles->find($battleId);
        if ($battle === null || $sessionId === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game action battle was not found');
        }

        if ($battle->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        return $battle;
    }

    /**
     * Проектор конфликта листа актора.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $actor Актор.
     * @param int $actorUserId Пользователь.
     * @param bool $viewAll Полный просмотр.
     *
     * @return Closure(
     *     CharacterConflictException|GameEconomyConflictException
     * ): array{choices: array<string, mixed>, sheet: array<string, mixed>} Проектор.
     */
    public function conflictProjection(
        GameRecord $game,
        array $actor,
        int $actorUserId,
        bool $viewAll,
    ): Closure {
        return fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->ports->sheets
            ->conflicts->projectWriteConflict($game, $actor, $exception, $actorUserId, $viewAll);
    }

    /**
     * Сохраняет заявку реакции в строку удара.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $input Вход.
     * @param int $strikeId Строка.
     * @param string $key Колонка supporters или coverers.
     * @param list<array<string, mixed>> $entries Заявки.
     *
     * @return GameBattleRecord Бой.
     *
     * @throws GameBattleConflictException Если версия другая.
     */
    public function saveStrike(
        GameBattleRecord $battle,
        array $input,
        int $strikeId,
        string $key,
        array $entries,
    ): GameBattleRecord {
        $this->ports->actions->answerStrike($strikeId, [$key => $entries]);

        return $this->ports->battles->replaceTracked(
            $battle->getId(),
            $input['expectedBattle'],
            null,
            null,
            null,
            null,
        );
    }
}