<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Dto\Action\ApplyCharacterActualPatchInput;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Exception\CharacterSaveRejectedException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;

/**
 * HTTP-сценарий патча actual: владелец, затем порт без проверки актора.
 */
final class CharacterActualPatch
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param ICharacters $characters Строка.
     * @param ICharacterActualMutations $mutations Порт записи.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly ICharacters $characters,
        private readonly ICharacterActualMutations $mutations,
    ) {
    }

    /**
     * Пишет количество владельцу.
     *
     * @param ApplyCharacterActualPatchInput $input JSON.
     *
     * @return array{characterId: int, actualVersion: int} Id и новая версия.
     *
     * @throws ActionException Если нет актора.
     * @throws CharacterNotFoundException Если лист чужой или его нет.
     * @throws CharacterConflictException Если версия устарела.
     * @throws CharacterInvalidException Если операция.
     * @throws CharacterSaveRejectedException Если validate вернул problems.
     */
    public function apply(ApplyCharacterActualPatchInput $input): array
    {
        $this->owned($input->characterId, $this->userAccess->requireActor()->getUserId());
        $record = $this->mutations->apply($input->characterId, $input->expectedActualVersion, $input->operations);

        return $this->view($record);
    }

    /**
     * Строка только владельца.
     *
     * @param int $characterId Персонаж.
     * @param int $actorUserId Актор.
     *
     * @return void
     *
     * @throws CharacterNotFoundException Если нет или владелец другой.
     */
    private function owned(int $characterId, int $actorUserId): void
    {
        if ($this->characters->get($characterId)->getOwnerId() !== $actorUserId) {
            throw new CharacterNotFoundException();
        }
    }

    /**
     * Ответ без листа.
     *
     * @param CharacterRecord $record Строка после записи.
     *
     * @return array{characterId: int, actualVersion: int} Id и версия.
     */
    private function view(CharacterRecord $record): array
    {
        return [
            'characterId' => $record->getId(),
            'actualVersion' => $record->getActualVersion(),
        ];
    }
}
