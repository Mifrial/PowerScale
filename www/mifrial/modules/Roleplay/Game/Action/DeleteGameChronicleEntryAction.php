<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\DeleteGameChronicleEntryInput;
use Mifrial\Roleplay\Game\Service\GameChronicleHttp;

/**
 * Удаление записи летописи.
 */
final class DeleteGameChronicleEntryAction implements IActionHandler
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
     * Снимает запись.
     *
     * @param DeleteGameChronicleEntryInput $input JSON.
     *
     * @return null Пусто.
     */
    public function handle(DeleteGameChronicleEntryInput $input): null
    {
        return $this->chronicleHttp->delete($input);
    }
}
