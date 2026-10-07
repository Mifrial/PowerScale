<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.replaceShop.
 */
final class ReplaceGameShopInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param array $positions Будущий набор.
     * @param array $expectedPositions Текущие версии.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly array $positions,
        public readonly array $expectedPositions,
    ) {
    }
}
