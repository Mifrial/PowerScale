<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.getCheckOffers.
 */
final class GetGameCheckOffersInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param array{type: string, id: int}|null $entity Участник или null.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly ?array $entity = null,
    ) {
    }
}
