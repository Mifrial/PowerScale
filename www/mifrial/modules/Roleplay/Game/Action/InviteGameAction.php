<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\InviteGameInput;
use Mifrial\Roleplay\Game\Service\GameAdmissionHttp;

/**
 * Приглашение в игру.
 */
final class InviteGameAction implements IActionHandler
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
     * Пишет приглашение.
     *
     * @param InviteGameInput $input JSON.
     *
     * @return array<string, mixed> Приглашение.
     */
    public function handle(InviteGameInput $input): array
    {
        return $this->admissionHttp->invite($input);
    }
}
