<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\ProposeGameCheckInput;
use Mifrial\Roleplay\Game\Service\GameCheckHttp;

/**
 * Pairwise-предложение.
 */
final class ProposeGameCheckAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameCheckHttp $checkHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCheckHttp $checkHttp,
    ) {
    }

    /**
     * Открывает предложение.
     *
     * @param ProposeGameCheckInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(ProposeGameCheckInput $input): array
    {
        return $this->checkHttp->proposeCheck($input);
    }
}
