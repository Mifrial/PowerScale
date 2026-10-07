<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\DeclareGameCheckInput;
use Mifrial\Roleplay\Game\Service\GameCheckHttp;

/**
 * Соло-проверка.
 */
final class DeclareGameCheckAction implements IActionHandler
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
     * Считает соло.
     *
     * @param DeclareGameCheckInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(DeclareGameCheckInput $input): array
    {
        return $this->checkHttp->declareCheck($input);
    }
}
