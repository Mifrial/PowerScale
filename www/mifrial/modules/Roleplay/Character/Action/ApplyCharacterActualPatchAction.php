<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Character\Dto\Action\ApplyCharacterActualPatchInput;
use Mifrial\Roleplay\Character\Service\CharacterActualPatch;

/**
 * Точечная запись actual владельцем.
 */
final class ApplyCharacterActualPatchAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param CharacterActualPatch $characterActualPatch Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterActualPatch $characterActualPatch,
    ) {
    }

    /**
     * Пишет патч.
     *
     * @param ApplyCharacterActualPatchInput $input JSON.
     *
     * @return array{characterId: int, actualVersion: int} Id и версия.
     */
    public function handle(ApplyCharacterActualPatchInput $input): array
    {
        return $this->characterActualPatch->apply($input);
    }
}
