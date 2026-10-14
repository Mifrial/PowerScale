<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GameWoundInput;
use Mifrial\Roleplay\Game\Service\GameWoundHttp;

/**
 * Зажим, отпускание или перевязка.
 */
final class GameWoundAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameWoundHttp $woundHttp Сценарий.
     *
     * @return void
     */
    public function __construct(private readonly GameWoundHttp $woundHttp)
    {
    }

    /**
     * Выполняет команду.
     *
     * @param GameWoundInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(GameWoundInput $input): array
    {
        return $this->woundHttp->act($input);
    }
}
