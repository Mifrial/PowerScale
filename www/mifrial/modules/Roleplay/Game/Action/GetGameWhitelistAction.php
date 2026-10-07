<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameWhitelistInput;
use Mifrial\Roleplay\Game\Service\GameAdmissionHttp;

/**
 * Чтение whitelist.
 */
final class GetGameWhitelistAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameAdmissionHttp $admissionHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameAdmissionHttp $admissionHttp,
    ) {
    }

    /**
     * Возвращает набор.
     *
     * @param GetGameWhitelistInput $input JSON.
     *
     * @return array<string, mixed> Список id.
     */
    public function handle(GetGameWhitelistInput $input): array
    {
        return $this->admissionHttp->getWhitelist($input);
    }
}
