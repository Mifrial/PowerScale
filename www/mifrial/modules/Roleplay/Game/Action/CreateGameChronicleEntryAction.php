<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\CreateGameChronicleEntryInput;
use Mifrial\Roleplay\Game\Service\GameChronicleHttp;

/**
 * Создание записи летописи.
 */
final class CreateGameChronicleEntryAction implements IActionHandler
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
     * Пишет запись.
     *
     * @param CreateGameChronicleEntryInput $input JSON.
     *
     * @return array<string, mixed> Запись.
     */
    public function handle(CreateGameChronicleEntryInput $input): array
    {
        return $this->chronicleHttp->create($input);
    }
}
