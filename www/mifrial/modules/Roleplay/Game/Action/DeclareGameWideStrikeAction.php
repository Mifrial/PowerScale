<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\DeclareGameWideStrikeInput;
use Mifrial\Roleplay\Game\Service\GameWideStrikeHttp;

/**
 * Решение широкой атаки.
 */
final class DeclareGameWideStrikeAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameWideStrikeHttp $strikeHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameWideStrikeHttp $strikeHttp,
    ) {
    }

    /**
     * Открывает широкий удар.
     *
     * @param DeclareGameWideStrikeInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(DeclareGameWideStrikeInput $input): array
    {
        return $this->strikeHttp->declareWideStrike($input);
    }
}
