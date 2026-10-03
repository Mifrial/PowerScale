<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Dto\Action\GetCharacterInput;
use Mifrial\Roleplay\Character\Dto\Action\UpdateCharacterOwnerNotesInput;
use Mifrial\Roleplay\Character\Dto\Action\UpdateCharacterVisibilityInput;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Service\Read\CharacterViewAssembler;

/**
 * HTTP чтения, заметок и видимости. Таблицы не открывает.
 */
final class CharacterRead
{
    /**
     * Собирает сценарий.
     *
     * @param IUserAccess $userAccess Актор запроса.
     * @param CharacterSheetAccess $sheetAccess Право на лист.
     * @param CharacterViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly CharacterSheetAccess $sheetAccess,
        private readonly CharacterViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Список видимых листов.
     *
     * @return list<array<string, mixed>> Проекции.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws CharacterInvalidException Если выборка битая.
     */
    public function getList(): array
    {
        $actor = $this->userAccess->requireActor();
        $rows = [];
        foreach ($this->sheetAccess->visibleList($actor) as $record) {
            $rows[] = $this->viewAssembler->listItem($record);
        }

        return $rows;
    }

    /**
     * Detail своего или чужого листа.
     *
     * @param GetCharacterInput $input Id.
     *
     * @return array<string, mixed> JSON.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws CharacterNotFoundException Если лист недоступен.
     * @throws CharacterInvalidException Если строка битая.
     */
    public function get(GetCharacterInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->viewAssembler->detail($this->sheetAccess->open($actor, $input->id));
    }

    /**
     * Меняет видимость владельца.
     *
     * @param UpdateCharacterVisibilityInput $input Секции и зрители.
     *
     * @return array<string, mixed> Вид владельца.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws CharacterNotFoundException Если чужой.
     * @throws CharacterInvalidException Если вход секций.
     */
    public function updateVisibility(UpdateCharacterVisibilityInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->viewAssembler->detail($this->sheetAccess->replaceVisibility(
            $actor,
            $input->id,
            $input->visibilityFields,
            $input->viewers,
        ));
    }

    /**
     * Меняет заметки владельца.
     *
     * @param UpdateCharacterOwnerNotesInput $input Текст.
     *
     * @return array<string, mixed> Вид владельца.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws CharacterNotFoundException Если чужой.
     * @throws CharacterInvalidException Если поле.
     */
    public function updateOwnerNotes(UpdateCharacterOwnerNotesInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->viewAssembler->detail(
            $this->sheetAccess->replaceOwnerNotes($actor, $input->id, $input->ownerNotes),
        );
    }
}
