<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор выбора защиты.
 */
final class GameStrikeBodyDefense
{
    /**
     * Создаёт разбор защиты.
     *
     * @param GameStrikeReaction $reaction Строка реакции.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeReaction $reaction,
    ) {
    }

    /**
     * Выбор защиты.
     *
     * @param array<string, mixed> $defense Тело.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(array $defense): array
    {
        return $this->reaction->choice($defense, ['reaction'], false, 'Invalid parameter: defense');
    }
}
