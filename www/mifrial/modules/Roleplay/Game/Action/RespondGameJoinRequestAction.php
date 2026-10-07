<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\RespondGameJoinRequestInput;
use Mifrial\Roleplay\Game\Service\GameAdmissionHttp;

/**
 * Ответ на заявку.
 */
final class RespondGameJoinRequestAction implements IActionHandler
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
     * @param RespondGameJoinRequestInput $input JSON.
     *
     * @return array<string, mixed> Заявка.
     */
    public function handle(RespondGameJoinRequestInput $input): array
    {
        return $this->admissionHttp->respondJoinRequest($input);
    }
}
