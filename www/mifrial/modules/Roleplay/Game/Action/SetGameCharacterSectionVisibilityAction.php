<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\SetGameCharacterSectionVisibilityInput;
use Mifrial\Roleplay\Game\Service\GameCharacterSectionHttp;

/**
 * Секции видимости персонажа в игре.
 */
final class SetGameCharacterSectionVisibilityAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameCharacterSectionHttp $sectionHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCharacterSectionHttp $sectionHttp,
    ) {
    }

    /**
     * Пишет секции.
     *
     * @param SetGameCharacterSectionVisibilityInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     */
    public function handle(SetGameCharacterSectionVisibilityInput $input): array
    {
        return $this->sectionHttp->set($input);
    }
}
