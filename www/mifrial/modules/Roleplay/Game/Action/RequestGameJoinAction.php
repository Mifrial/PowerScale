<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\RequestGameJoinInput;
use Mifrial\Roleplay\Game\Service\GameAdmissionHttp;

/**
 * Заявка на вступление.
 */
final class RequestGameJoinAction implements IActionHandler
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
     * Пишет заявку.
     *
     * @param RequestGameJoinInput $input JSON.
     *
     * @return array<string, mixed> Заявка.
     */
    public function handle(RequestGameJoinInput $input): array
    {
        return $this->admissionHttp->requestJoin($input);
    }
}
