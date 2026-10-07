<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameChronicleEntriesInput;
use Mifrial\Roleplay\Game\Service\GameChronicleHttp;

/**
 * Список записей летописи.
 */
final class GetGameChronicleEntriesAction implements IActionHandler
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
     * Отдаёт список.
     *
     * @param GetGameChronicleEntriesInput $input JSON.
     *
     * @return list<array<string, mixed>> Записи.
     */
    public function handle(GetGameChronicleEntriesInput $input): array
    {
        return $this->chronicleHttp->getEntries($input);
    }
}
