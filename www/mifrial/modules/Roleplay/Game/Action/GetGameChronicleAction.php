<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameChronicleInput;
use Mifrial\Roleplay\Game\Service\GameChronicleHttp;

/**
 * Шапка летописи.
 */
final class GetGameChronicleAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameChronicleHttp $chronicleHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameChronicleHttp $chronicleHttp,
    ) {
    }

    /**
     * Отдаёт шапку.
     *
     * @param GetGameChronicleInput $input JSON.
     *
     * @return array<string, mixed> Шапка.
     */
    public function handle(GetGameChronicleInput $input): array
    {
        return $this->chronicleHttp->getChronicle($input);
    }
}
