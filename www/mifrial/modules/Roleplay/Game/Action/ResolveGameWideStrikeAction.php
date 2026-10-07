<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\ResolveGameWideStrikeInput;
use Mifrial\Roleplay\Game\Service\GameWideStrikeHttp;

/**
 * Ответ защиты широкого удара.
 */
final class ResolveGameWideStrikeAction implements IActionHandler
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
     * Закрывает широкий удар.
     *
     * @param ResolveGameWideStrikeInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(ResolveGameWideStrikeInput $input): array
    {
        return $this->strikeHttp->resolveWideStrike($input);
    }
}
