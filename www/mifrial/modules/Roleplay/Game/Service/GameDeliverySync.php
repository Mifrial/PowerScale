<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Http\SseEmitter;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Interface\Service\IGameDelivery;
use Mifrial\Roleplay\Game\Interface\Service\IGameSyncClock;

/**
 * Держит /api/game/sync, пока клиент не закрыл соединение.
 */
final class GameDeliverySync
{
    private const TICK_SECONDS = 1;

    private const COMMENT_TICKS = 15;

    /**
     * Создаёт цикл.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGameDelivery $delivery Фасад.
     * @param IGameSyncClock $clock Пауза и обрыв.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGameDelivery $delivery,
        private readonly IGameSyncClock $clock,
    ) {
    }

    /**
     * Пишет кадры, пока соединение живо.
     *
     * @param IHttpRequest $httpRequest Query.
     * @param SseEmitter $sseEmitter Байты.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED или INVALID_PARAMS.
     */
    public function run(IHttpRequest $httpRequest, SseEmitter $sseEmitter): void
    {
        $actor = $this->userAccess->requireActor();
        $gameId = $this->gameId($httpRequest);
        $lastCursor = $this->lastCursor($httpRequest);
        $viewAll = $actor->hasKey(GamePermissionKeys::VIEW_ALL);
        $this->delivery->catchUp($gameId);
        $frame = $this->delivery->tail($gameId, $actor->getUserId(), $viewAll, $lastCursor);
        $sseEmitter->start();
        $sseEmitter->writeEvent('sync', $frame);
        $this->loop($sseEmitter, $gameId, $actor->getUserId(), $viewAll, $frame['cursor']);
    }

    /**
     * Тики до обрыва.
     *
     * @param SseEmitter $sseEmitter Байты.
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ.
     * @param int $lastCursor Уже отданный курсор.
     *
     * @return void
     */
    private function loop(
        SseEmitter $sseEmitter,
        int $gameId,
        int $actorUserId,
        bool $viewAll,
        int $lastCursor,
    ): void {
        $cursor = $lastCursor;
        $quiet = 0;
        while (!$this->clock->isAborted()) {
            $this->delivery->catchUp($gameId);
            $frame = $this->delivery->tail($gameId, $actorUserId, $viewAll, $cursor);
            if ($frame['cursor'] > $cursor) {
                $cursor = $frame['cursor'];
            }

            if ($frame['events'] !== []) {
                $sseEmitter->writeEvent('sync', $frame);
                $quiet = 0;
            } else {
                $quiet++;
                if ($quiet >= self::COMMENT_TICKS) {
                    $sseEmitter->writeComment('ping');
                    $quiet = 0;
                }
            }

            $this->clock->sleep(self::TICK_SECONDS);
        }
    }

    /**
     * gameId из query.
     *
     * @param IHttpRequest $httpRequest Снимок.
     *
     * @return int Id.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function gameId(IHttpRequest $httpRequest): int
    {
        $raw = $httpRequest->getQueryValue('gameId');
        if (!is_string($raw) || preg_match('/^[1-9][0-9]*$/D', $raw) !== 1) {
            throw new ActionException('INVALID_PARAMS', 'gameId is invalid');
        }

        return (int) $raw;
    }

    /**
     * lastCursor или null, если ключа нет.
     *
     * @param IHttpRequest $httpRequest Снимок.
     *
     * @return int|null Курсор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function lastCursor(IHttpRequest $httpRequest): ?int
    {
        $raw = $httpRequest->getQueryValue('lastCursor');
        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_string($raw) || preg_match('/^(0|[1-9][0-9]*)$/D', $raw) !== 1) {
            throw new ActionException('INVALID_PARAMS', 'lastCursor is invalid');
        }

        return (int) $raw;
    }
}
