<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Character\Dto\Action\GetCharacterInput;
use Mifrial\Roleplay\Character\Service\CharacterRead;

/**
 * Detail персонажа с маской секций.
 */
final class GetCharacterAction implements IActionHandler
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
     * Возвращает лист.
     *
     * @param GetCharacterInput $input Id.
     *
     * @return array<string, mixed> Detail.
     */
    public function handle(GetCharacterInput $input): array
    {
        return $this->characterRead->get($input);
    }
}
