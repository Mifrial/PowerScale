<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Service\GameAdmissionHttp;

/**
 * Свои приглашения.
 */
final class GetMyGameInvitationsAction implements IActionHandler
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
     * Возвращает приглашения актора.
     *
     * @return list<array<string, mixed>> Список.
     */
    public function handle(): array
    {
        return $this->admissionHttp->getMyInvitations();
    }
}
