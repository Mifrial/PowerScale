<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\UpdateGameChronicleEntryInput;
use Mifrial\Roleplay\Game\Service\GameChronicleHttp;

/**
 * Правка записи летописи.
 */
final class UpdateGameChronicleEntryAction implements IActionHandler
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
     * Меняет запись.
     *
     * @param UpdateGameChronicleEntryInput $input JSON.
     *
     * @return array<string, mixed> Запись.
     */
    public function handle(UpdateGameChronicleEntryInput $input): array
    {
        return $this->chronicleHttp->update($input);
    }
}
