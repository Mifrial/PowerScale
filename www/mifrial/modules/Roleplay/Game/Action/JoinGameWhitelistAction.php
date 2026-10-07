<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\JoinGameWhitelistInput;
use Mifrial\Roleplay\Game\Service\GameAdmissionHttp;

/**
 * Вступление по whitelist.
 */
final class JoinGameWhitelistAction implements IActionHandler
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
     * Пишет участника.
     *
     * @param JoinGameWhitelistInput $input JSON.
     *
     * @return array<string, mixed> Участник.
     */
    public function handle(JoinGameWhitelistInput $input): array
    {
        return $this->admissionHttp->joinWhitelist($input);
    }
}
