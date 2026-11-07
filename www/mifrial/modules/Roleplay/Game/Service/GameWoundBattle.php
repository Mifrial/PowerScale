<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Бой раны: долг актора и карта ожидания.
 */
final class GameWoundBattle
{
    /**
     * Создаёт боевую часть.
     *
     * @param GameWoundRows $rows Чтение боя.
     * @param GameBattleRepository $battles Бои.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameWoundRows $rows,
        private readonly GameBattleRepository $battles,
        private readonly GameStrikeSheetWrites $sheetWrites,
    ) {
    }

    /**
     * Открывает долг, если текущего пула не хватает.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $prepared Подготовка.
     * @param bool $paid Трата уже списана.
     *
     * @return array<string, mixed>|null Итог долга или null.
     *
     * @throws GameInvalidException Если долг ещё открыт.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    public function pay(GameRecord $game, array $body, array $prepared, bool $paid): ?array
    {
        $battle = $this->rows->battleOf($body);
        if ($battle === null || $paid) {
            return null;
        }

        $debt = new GameActionDebt();
        $debtKey = $debt->key($prepared['actor']['kind'], $prepared['actor']['id']);
        $debt->assertFree($battle->getActionDebt(), $debtKey);
        $planned = $this->plan($debt, $body, $prepared);
        if ($planned['open']) {
            return null;
        }

        return $this->open($game, $body, $prepared, $battle, $debtKey, $planned);
    }

    /**
     * Пишет карту, если тело назвало бой.
     *
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $pending Карта.
     *
     * @return int|null Новая версия боя или null.
     *
     * @throws GameInvalidException Если версия боя не целая.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия чужая.
     */
    public function store(array $body, array $pending): ?int
    {
        $battleId = $body['battleId'] ?? 0;
        $expected = $body['expectedBattleVersion'] ?? null;
        if (!is_int($battleId) || $battleId < 1) {
            return null;
        }

        if (!is_int($expected)) {
            throw new GameInvalidException('Game wound battle version is invalid');
        }

        return $this->battles->replacePending($battleId, $expected, $pending)->getStateVersion();
    }

    /**
     * Делит трату F1 и слот долга.
     *
     * @param GameActionDebt $debt Долг.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $prepared Подготовка.
     *
     * @return array<string, mixed> План.
     *
     * @throws GameInvalidException Если лист без ресурсов.
     */
    private function plan(GameActionDebt $debt, array $body, array $prepared): array
    {
        return $debt->plan(
            $prepared['slice'],
            $prepared['actorSheet']['sheet'],
            $prepared['priced']['spends'],
            $debt->refillCode($prepared['slice']),
            'wound',
            $body,
        );
    }

    /**
     * Пишет оплаченную часть и слот долга.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $prepared Подготовка.
     * @param GameBattleRecord $battle Бой.
     * @param string $debtKey Ключ актора.
     * @param array<string, mixed> $planned План долга.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если лист отвергнут.
     * @throws GameNotFoundException Если строки нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function open(
        GameRecord $game,
        array $body,
        array $prepared,
        GameBattleRecord $battle,
        string $debtKey,
        array $planned,
    ): array {
        $version = $this->sheetWrites->writeResources(
            $game,
            $prepared['actor']['kind'],
            $prepared['actor']['id'],
            $prepared['actorSheet']['actualVersion'],
            $planned['spends'],
        );
        $saved = $this->saveSlot($body, $prepared, $battle, $debtKey, $planned);

        return [
            'operation' => $prepared['operation'],
            'target' => $prepared['target'],
            'stateId' => $prepared['stateId'],
            'sheetVersion' => $version,
            'battleVersion' => $saved->getStateVersion(),
            'opened' => false,
            'debt' => true,
            'remaining' => $planned['slot']['remaining'] ?? 0,
        ];
    }

    /**
     * Пишет слот долга и карту ожидания.
     *
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $prepared Подготовка.
     * @param GameBattleRecord $battle Бой.
     * @param string $debtKey Ключ актора.
     * @param array<string, mixed> $planned План долга.
     *
     * @return GameBattleRecord Бой.
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия чужая.
     */
    private function saveSlot(
        array $body,
        array $prepared,
        GameBattleRecord $battle,
        string $debtKey,
        array $planned,
    ): GameBattleRecord {
        $debtMap = $battle->getActionDebt();
        $debtMap[$debtKey] = $planned['slot'];

        return $this->battles->replaceTracked(
            $battle->getId(),
            $this->expectedBattle($body, $battle),
            $prepared['priced']['pending'],
            $debtMap,
            null,
            null,
        );
    }

    /**
     * Ожидаемая версия боя из тела.
     *
     * @param array<string, mixed> $body Тело.
     * @param GameBattleRecord $battle Бой.
     *
     * @return int Версия.
     */
    private function expectedBattle(array $body, GameBattleRecord $battle): int
    {
        $expected = $body['expectedBattleVersion'] ?? null;

        return is_int($expected) ? $expected : $battle->getStateVersion();
    }
}
