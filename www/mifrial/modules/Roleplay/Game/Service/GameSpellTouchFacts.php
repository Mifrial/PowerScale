<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\AttackHit;

/**
 * Доступность заклинания, доставляемого ударом касания.
 */
final class GameSpellTouchFacts
{
    /**
     * Создаёт проверку касания.
     *
     * @param GameSpellCastFacts $facts Общая подготовка каста.
     * @param GameStrikeSheets $sheets Снимки цели касания.
     *
     * @return void
     */
    public function __construct(
        private readonly GameSpellCastFacts $facts,
        private readonly GameStrikeSheets $sheets,
    ) {
    }

    /**
     * Проверяет заклинание и возвращает траты для объявления удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор удара.
     * @param array<string, mixed> $payload Блок spellCast.
     *
     * @return array{payload: array<string, mixed>, spends: array<int, \Mifrial\Roleplay\Character\Dto\ResourceSpend>} Итог.
     *
     * @throws GameInvalidException Если заклинание не касание.
     */
    public function prepare(GameRecord $game, array $choice, array $payload): array
    {
        $prepared = $this->prepared($game, $choice, $payload);
        if (!$prepared->spell->getBase()->getHitResolution() instanceof AttackHit) {
            throw new GameInvalidException('Game spell is not a touch attack');
        }

        return ['payload' => $payload, 'spends' => $prepared->spends];
    }

    /**
     * Свежая подготовка каста для завершения удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор удара.
     * @param array<string, mixed> $payload Блок spellCast.
     *
     * @return GameSpellCastPrepared Подготовка.
     *
     * @throws GameInvalidException Если заклинание не касание.
     */
    public function prepared(GameRecord $game, array $choice, array $payload): GameSpellCastPrepared
    {
        $defender = $choice['defender'] ?? null;
        if (!is_array($defender)) {
            throw new GameInvalidException('Game spell touch target is invalid');
        }

        $body = [
            'gameId' => $game->getId(),
            'battleId' => 0,
            'idempotencyKey' => '',
            'expectedVersion' => 0,
            'expectedSheetVersion' => 0,
            'actor' => $choice['attacker'],
            'spellCode' => $payload['spellCode'],
            'pathCode' => $payload['pathCode'],
            'parameters' => $payload['parameters'],
            'usedPower' => $payload['usedPower'],
            'target' => $payload['spellTarget'] ?? null,
            'distanceIpari' => 0,
        ];
        $prepared = $this->facts->prepare($game, $body, $this->touchTarget($game, $defender));
        if (!$prepared->spell->getBase()->getHitResolution() instanceof AttackHit) {
            throw new GameInvalidException('Game spell is not a touch attack');
        }

        return $prepared;
    }

    /**
     * Цель касания из её снимка.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $defender Защитник.
     *
     * @return array{
     *     type: string,
     *     id: int,
     *     choices: array<string, mixed>,
     *     sheet: array<string, mixed>,
     *     version: int
     * } Цель.
     *
     * @throws GameInvalidException Если снимок битый.
     */
    private function touchTarget(GameRecord $game, array $defender): array
    {
        $snapshot = $this->sheets->snapshot($game, $defender['type'], $defender['id']);
        $choices = $snapshot['sheet']['choices'] ?? null;
        $sheet = $snapshot['sheet']['sheet'] ?? null;
        $version = $snapshot['actualVersion'] ?? null;
        if (!is_array($choices) || !is_array($sheet) || !is_int($version)) {
            throw new GameInvalidException('Game spell touch sheet is invalid');
        }

        return [
            'type' => $defender['type'],
            'id' => $defender['id'],
            'choices' => $choices,
            'sheet' => $this->facts->compute($game, $choices, $sheet),
            'version' => $version,
        ];
    }
}
