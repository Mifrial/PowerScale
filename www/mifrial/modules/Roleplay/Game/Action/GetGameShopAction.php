<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameShopInput;
use Mifrial\Roleplay\Game\Service\GameEconomyHttp;

/**
 * Чтение магазина.
 */
final class GetGameShopAction implements IActionHandler
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
     * Отдаёт позиции.
     *
     * @param GetGameShopInput $input JSON.
     *
     * @return array<string, mixed> Позиции.
     */
    public function handle(GetGameShopInput $input): array
    {
        return $this->economyHttp->getShop($input);
    }
}
