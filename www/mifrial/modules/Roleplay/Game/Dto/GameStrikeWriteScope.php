<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Права записи листа цели удара.
 */
final class GameStrikeWriteScope
{
    /**
     * Создаёт область записи.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return void
     */
    public function __construct(
        public readonly int $actorUserId,
        public readonly bool $viewAll,
    ) {
    }
}
