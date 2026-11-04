<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\CancelGameCheckInput;
use Mifrial\Roleplay\Game\Service\GameCheckHttp;

/**
 * Отмена предложения проверки.
 */
final class CancelGameCheckAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameCheckHttp $check Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCheckHttp $check,
    ) {
    }

    /**
     * Отменяет предложение.
     *
     * @param CancelGameCheckInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(CancelGameCheckInput $input): array
    {
        return $this->check->cancelCheck($input);
    }
}
