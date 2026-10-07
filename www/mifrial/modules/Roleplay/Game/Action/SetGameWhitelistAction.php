<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\SetGameWhitelistInput;
use Mifrial\Roleplay\Game\Service\GameAdmissionHttp;

/**
 * Замена whitelist.
 */
final class SetGameWhitelistAction implements IActionHandler
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
     * Пишет набор.
     *
     * @param SetGameWhitelistInput $input JSON.
     *
     * @return array<string, mixed> Список id.
     */
    public function handle(SetGameWhitelistInput $input): array
    {
        return $this->admissionHttp->setWhitelist($input);
    }
}
