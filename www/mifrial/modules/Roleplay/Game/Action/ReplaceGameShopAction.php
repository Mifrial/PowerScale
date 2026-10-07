<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\ReplaceGameShopInput;
use Mifrial\Roleplay\Game\Service\GameEconomyHttp;

/**
 * Замена набора магазина.
 */
final class ReplaceGameShopAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameEconomyHttp $economyHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameEconomyHttp $economyHttp,
    ) {
    }

    /**
     * Пишет набор.
     *
     * @param ReplaceGameShopInput $input JSON.
     *
     * @return array<string, mixed> Позиции.
     */
    public function handle(ReplaceGameShopInput $input): array
    {
        return $this->economyHttp->replaceShop($input);
    }
}
