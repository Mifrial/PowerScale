<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameInvitationsInput;
use Mifrial\Roleplay\Game\Service\GameAdmissionHttp;

/**
 * Список приглашений игры.
 */
final class GetGameInvitationsAction implements IActionHandler
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
     * Возвращает приглашения.
     *
     * @param GetGameInvitationsInput $input JSON.
     *
     * @return list<array<string, mixed>> Список.
     */
    public function handle(GetGameInvitationsInput $input): array
    {
        return $this->admissionHttp->getInvitations($input);
    }
}
