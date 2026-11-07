<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;

/**
 * Открытие удара: проверки, транзакция и сигнал.
 */
final class GameStrikeOpenFlow
{
    /**
     * Создаёт сценарий открытия.
     *
     * @param GameActionRepository $actions Действия.
     * @param GameStrikeAccess $access Права.
     * @param GameStrikeBattleLookup $battles Бой.
     * @param GameStrikeOpenPreparation $preparation Подготовка выбора.
     * @param GameStrikeOpenTransaction $transaction Транзакция.
     * @param GameStrikeAnnounce $announce Сигнал.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionRepository $actions,
        private readonly GameStrikeAccess $access,
        private readonly GameStrikeBattleLookup $battles,
        private readonly GameStrikeOpenPreparation $preparation,
        private readonly GameStrikeOpenTransaction $transaction,
        private readonly GameStrikeAnnounce $announce,
    ) {
    }

    /**
     * Пишет открытый удар и версию боя.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $choice Выбор.
     * @param int $actorUserId Актор.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если удар уже открыт.
     */
    public function open(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choice,
        int $actorUserId,
    ): array {
        $body = $this->body($choice, $battleId, $expectedBattleVersion);
        $battle = $this->battles->of($sessionId, $battleId, $expectedBattleVersion);
        $this->assertOpenable($battleId, $choice);
        $prepared = $this->preparation->prepare($game, $battle, $choice);
        $result = $this->transaction->run(
            $game,
            $sessionId,
            $battle,
            $idempotencyKey,
            $expectedBattleVersion,
            $body,
            $prepared,
            $actorUserId,
        );
        $this->announce->recorded($game->getId(), $idempotencyKey);

        return $result;
    }

    /**
     * Отпечаток запроса — только присланное клиентом.
     *
     * Производные поля (maneuver, processContext) в тело не идут: иначе replay
     * того же ключа сверяет их с телом из ready() и даёт ложный GAME_CONFLICT.
     *
     * @param array<string, mixed> $choice Выбор.
     * @param int $battleId Бой.
     * @param int $expectedBattleVersion Версия.
     *
     * @return array<string, mixed> Тело.
     */
    private function body(array $choice, int $battleId, int $expectedBattleVersion): array
    {
        return [
            'attack' => $choice,
            'battleId' => $battleId,
            'expectedVersion' => $expectedBattleVersion,
        ];
    }

    /**
     * Права ростера и отсутствие другого открытого удара.
     *
     * @param int $battleId Бой.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return void
     *
     * @throws GameInvalidException Если удар уже открыт.
     */
    private function assertOpenable(int $battleId, array $choice): void
    {
        $this->access->assertRoster($battleId, $choice['attacker'], [$choice['defender']]);
        if ($this->actions->findOpenAny($battleId) !== null) {
            throw new GameInvalidException('Game strike is already open');
        }
    }
}
