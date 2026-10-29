<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameActionOptionsInput;
use Mifrial\Roleplay\Game\Service\GameActionOptionsHttp;

/**
 * Опции действий участника.
 */
final class GetGameActionOptionsAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameActionOptionsHttp $options Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionOptionsHttp $options,
    ) {
    }

    /**
     * Читает опции.
     *
     * @param GetGameActionOptionsInput $input JSON.
     *
     * @return array<string, mixed> Опции.
     */
    public function handle(GetGameActionOptionsInput $input): array
    {
        return $this->options->options($input);
    }
}
