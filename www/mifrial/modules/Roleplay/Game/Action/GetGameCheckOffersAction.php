<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameCheckOffersInput;
use Mifrial\Roleplay\Game\Service\GameCheckHttp;

/**
 * Ожидающие предложения проверок.
 */
final class GetGameCheckOffersAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameCheckHttp $check Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCheckHttp $check,
    ) {
    }

    /**
     * Читает предложения.
     *
     * @param GetGameCheckOffersInput $input JSON.
     *
     * @return array<int, array<string, mixed>> Предложения.
     */
    public function handle(GetGameCheckOffersInput $input): array
    {
        return $this->check->checkOffers($input);
    }
}
