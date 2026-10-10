<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Repository\GameWideStrikeCommandRepository;

/**
 * Совместимый адаптер поиска replay через общую границу.
 */
final class GameWideStrikeReplay
{
    /**
     * Создаёт адаптер.
     *
     * @param GameReplayTransaction $replayTransaction Общая граница.
     * @param GameWideStrikeCommandRepository $commands Журнал.
     *
     * @return void
     */
    public function __construct(
        private readonly GameReplayTransaction $replayTransaction,
        private readonly GameWideStrikeCommandRepository $commands,
    ) {
    }

    /**
     * Возвращает сохранённый итог.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $requestBody Тело.
     *
     * @return array<string, mixed>|null Итог или null.
     */
    public function find(int $gameId, string $idempotencyKey, array $requestBody): ?array
    {
        return $this->replayTransaction->find(
            fn (): ?array => $this->commands->findByKey($gameId, $idempotencyKey),
            $requestBody,
        );
    }
}
