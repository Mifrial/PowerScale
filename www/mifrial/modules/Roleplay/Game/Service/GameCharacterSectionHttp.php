<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\Action\SetGameCharacterSectionVisibilityInput;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;

/**
 * HTTP записи секций строки персонажа.
 */
final class GameCharacterSectionHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param GameCharacterSections $sections Запись колонки.
     * @param IGameMemberships $memberships Строка с reviewState.
     * @param GameCharacterViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCharacterSections $sections,
        private readonly IGameMemberships $memberships,
        private readonly GameCharacterViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Пишет секции и отдаёт строку.
     *
     * @param SetGameCharacterSectionVisibilityInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     */
    public function set(SetGameCharacterSectionVisibilityInput $input): array
    {
        $this->sections->set($input->gameId, $input->characterId, $input->sectionVisibility);

        return $this->viewAssembler->detail($this->memberships->get($input->gameId, $input->characterId));
    }
}
