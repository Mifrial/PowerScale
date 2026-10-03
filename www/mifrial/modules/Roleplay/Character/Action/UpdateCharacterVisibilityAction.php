<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Character\Dto\Action\UpdateCharacterVisibilityInput;
use Mifrial\Roleplay\Character\Service\CharacterRead;

/**
 * Видимость листа владельца.
 */
final class UpdateCharacterVisibilityAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param CharacterRead $characterRead Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterRead $characterRead,
    ) {
    }

    /**
     * Пишет секции и зрителей.
     *
     * @param UpdateCharacterVisibilityInput $input JSON.
     *
     * @return array<string, mixed> Вид владельца.
     */
    public function handle(UpdateCharacterVisibilityInput $input): array
    {
        return $this->characterRead->updateVisibility($input);
    }
}
