<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameJoinRequestsInput;
use Mifrial\Roleplay\Game\Service\GameAdmissionHttp;

/**
 * Список заявок игры.
 */
final class GetGameJoinRequestsAction implements IActionHandler
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
     * Возвращает заявки.
     *
     * @param GetGameJoinRequestsInput $input JSON.
     *
     * @return list<array<string, mixed>> Список.
     */
    public function handle(GetGameJoinRequestsInput $input): array
    {
        return $this->admissionHttp->getJoinRequests($input);
    }
}
