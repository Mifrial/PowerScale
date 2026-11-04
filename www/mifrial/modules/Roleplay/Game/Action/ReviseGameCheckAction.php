<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\ReviseGameCheckInput;
use Mifrial\Roleplay\Game\Service\GameCheckHttp;

/**
 * Правка предложения проверки.
 */
final class ReviseGameCheckAction implements IActionHandler
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
     * Правит предложение.
     *
     * @param ReviseGameCheckInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(ReviseGameCheckInput $input): array
    {
        return $this->check->reviseCheck($input);
    }
}
