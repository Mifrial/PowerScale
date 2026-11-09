<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;

/**
 * Серверная команда сотворения заклинания.
 */
final class GameSpellCast
{
    /**
     * Создаёт команду каста.
     *
     * @param GameStrikeReady $ready Повтор, права и сессия.
     * @param GameReplayTransaction $replay Граница replay.
     * @param GameCommandRepository $commands Журнал команд.
     * @param GameStrikeAnnounce $announce Сигнал доставки.
     * @param GameStrikeRepositories $repos Репозитории боя.
     * @param GameStrikeAccess $access Права и состав боя.
     * @param GameActionPerform $perform Траты.
     * @param GameSpellCastFacts $facts Подготовка.
     * @param GameSpellCastCheck $check Сложность и бросок.
     * @param GameSpellCastDamage $damage Урон.
     * @param GameSpellCastWrite $write Запись.
     * @param GameSpellCastChat $chat Чат.
     * @param GameSpellCastView $view Ответ.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeReady $ready,
        private readonly GameReplayTransaction $replay,
        private readonly GameCommandRepository $commands,
        private readonly GameStrikeAnnounce $announce,
        private readonly GameStrikeRepositories $repos,
        private readonly GameStrikeAccess $access,
        private readonly GameActionPerform $perform,
        private readonly GameSpellCastFacts $facts,
        private readonly GameSpellCastCheck $check,
        private readonly GameSpellCastDamage $damage,
        private readonly GameSpellCastWrite $write,
        private readonly GameSpellCastChat $chat,
        private readonly GameSpellCastView $view,
    ) {
    }

    /**
     * Выполняет каст.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Нормализованное тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия, ключ или лист чужие.
     */
    public function cast(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        $key = is_string($body['idempotencyKey'] ?? null) ? $body['idempotencyKey'] : '';
        $actor = is_array($body['actor'] ?? null) ? $body['actor'] : null;
        $ready = $this->ready->check(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $key,
            $body,
            $actor,
            'spell',
            'Game spell cast is invalid',
        );
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $game = $ready['game'];
        $sessionId = $ready['sessionId'];
        $execution = $this->replay->execute(
            fn (): ?array => $this->commands->findByKey($gameId, 'spell', $key),
            fn (): int => $this->commands->reserve($gameId, $sessionId, 'spell', $key, $body),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->work($game, $sessionId, $actorUserId, $body),
            $body,
        );
        if (!$execution['replay']) {
            $this->announce->recorded($gameId, $key, 'spell');
        }

        return $execution['result'];
    }

    /**
     * Проверяет бой и исполняет каст.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function work(GameRecord $game, int $sessionId, int $actorUserId, array $body): array
    {
        $battleId = is_int($body['battleId'] ?? null) ? $body['battleId'] : 0;
        $expected = is_int($body['expectedVersion'] ?? null) ? $body['expectedVersion'] : -1;
        $battle = $this->repos->battles->find($battleId);
        if ($battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game spell battle was not found');
        }

        if ($battle->getStateVersion() !== $expected) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        if ($this->repos->actions->findOpenAny($battleId) !== null) {
            throw new GameInvalidException('Game spell cast is blocked by an open action');
        }

        $actor = $this->sideOf($body['actor'] ?? null);
        $target = is_array($body['target'] ?? null) ? $this->sideOf($body['target']) : null;
        $this->access->assertRoster($battleId, $actor, $target === null ? [] : [$target]);

        $prepared = $this->facts->prepare($game, $body);
        $this->perform->assertSpends($game, $prepared->casterSheet, $prepared->spends);
        $check = $this->check->check($game, $prepared);
        $damage = $this->damage->apply($game, $prepared, $check['castOk']);
        $versions = $this->write->write($game, $prepared, $body, $damage);
        $this->chat->post($game, $actorUserId, $prepared, $battle, $check, $damage);

        return $this->view->build($prepared, $check, $damage, $versions);
    }

    /**
     * Сторона тела команды.
     *
     * @param mixed $value Значение.
     *
     * @return array{type: string, id: int} Сторона.
     *
     * @throws GameInvalidException Если сторона битая.
     */
    private function sideOf(mixed $value): array
    {
        $type = is_array($value) ? ($value['type'] ?? null) : null;
        $id = is_array($value) ? ($value['id'] ?? null) : null;
        if (($type !== 'character' && $type !== 'npc') || !is_int($id)) {
            throw new GameInvalidException('Game spell side is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }
}
