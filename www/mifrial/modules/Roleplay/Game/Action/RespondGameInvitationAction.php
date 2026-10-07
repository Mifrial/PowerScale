<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\RespondGameInvitationInput;
use Mifrial\Roleplay\Game\Service\GameAdmissionHttp;

/**
 * Ответ на приглашение.
 */
final class RespondGameInvitationAction implements IActionHandler
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
     * Пишет статус.
     *
     * @param RespondGameInvitationInput $input JSON.
     *
     * @return array<string, mixed> Приглашение.
     */
    public function handle(RespondGameInvitationInput $input): array
    {
        return $this->admissionHttp->respondInvitation($input);
    }
}
