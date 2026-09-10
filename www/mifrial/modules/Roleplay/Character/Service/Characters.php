<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Repository\CharacterRepository;

/**
 * Фасад actual-строки персонажа.
 */
final class Characters implements ICharacters
{
    /**
     * Создаёт фасад.
     *
     * @param CharacterRepository $characterRepository Строки.
     * @param IUserAccounts $userAccounts Учётки.
     * @param CharacterInputNormalizer $characterInputNormalizer Вход.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterRepository $characterRepository,
        private readonly IUserAccounts $userAccounts,
        private readonly CharacterInputNormalizer $characterInputNormalizer,
    ) {
    }

    /**
     * Добавляет строку. actual_version = 1.
     *
     * @param NewCharacter $new Поля insert.
     *
     * @return int Id.
     *
     * @throws CharacterInvalidException Если имя, ревизия, JSON или секции.
     * @throws CharacterNotFoundException Если нет учётки или часов.
     */
    public function add(NewCharacter $new): int
    {
        $values = $this->insertValues($new, DateTime::now());
        try {
            $this->userAccounts->getById($new->getOwnerUserId());
        } catch (UserNotFoundException $exception) {
            throw new CharacterNotFoundException('Character was not found', $exception);
        }

        return $this->characterRepository->add($values);
    }

    /**
     * Возвращает строку по id.
     *
     * @param int $id Идентификатор.
     *
     * @return CharacterRecord Персонаж.
     *
     * @throws CharacterNotFoundException Если строки нет.
     */
    public function get(int $id): CharacterRecord
    {
        return $this->characterRepository->getById($id);
    }

    /**
     * Пишет choices и sheet при совпадении expectedVersion.
     *
     * @param int $id Идентификатор.
     * @param array $choices Build.
     * @param array $sheet Кэш.
     * @param int $expectedVersion Текущий actual_version.
     *
     * @return CharacterRecord После записи.
     *
     * @throws CharacterInvalidException Если version меньше 1 или JSON.
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterConflictException Если version устарел.
     */
    public function replacePayload(int $id, array $choices, array $sheet, int $expectedVersion): CharacterRecord
    {
        $this->characterInputNormalizer->requirePositiveInt($expectedVersion, 'Character expected version is invalid');

        return $this->characterRepository->replacePayload(
            $id,
            $this->characterInputNormalizer->normalizePayload($choices),
            $this->characterInputNormalizer->normalizePayload($sheet),
            $expectedVersion,
            DateTime::now(),
        );
    }

    /**
     * Ставит active при совпадении expectedVersion.
     *
     * @param int $id Идентификатор.
     * @param bool $active Новый флаг.
     * @param int $expectedVersion Текущий actual_version.
     *
     * @return CharacterRecord После записи.
     *
     * @throws CharacterInvalidException Если version меньше 1.
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterConflictException Если version устарел.
     */
    public function setActive(int $id, bool $active, int $expectedVersion): CharacterRecord
    {
        $this->characterInputNormalizer->requirePositiveInt($expectedVersion, 'Character expected version is invalid');

        return $this->characterRepository->setActive($id, $active, $expectedVersion, DateTime::now());
    }

    /**
     * Колонки insert после нормализации.
     *
     * @param NewCharacter $new Вход.
     * @param DateTime $writtenAt Один now на created/updated.
     *
     * @return array<string, mixed> Колонки.
     *
     * @throws CharacterInvalidException Если имя, ids или JSON.
     */
    private function insertValues(NewCharacter $new, DateTime $writtenAt): array
    {
        $this->characterInputNormalizer->requirePositiveInt($new->getSpaceId(), 'Character space id is invalid');
        $this->characterInputNormalizer->requirePositiveInt(
            $new->getRulesRevision(),
            'Character rules revision is invalid',
        );
        $visibilityFields = $this->characterInputNormalizer->normalizeVisibilityFields($new->getVisibilityFields());

        return [
            'owner_id' => $new->getOwnerUserId(),
            'space_id' => $new->getSpaceId(),
            'rules_revision' => $new->getRulesRevision(),
            'name' => $this->characterInputNormalizer->normalizeName($new->getName()),
            'active' => $new->isActive(),
            'actual_version' => 1,
            'choices' => $this->characterInputNormalizer->normalizePayload($new->getChoices()),
            'sheet' => $this->characterInputNormalizer->normalizePayload($new->getSheet()),
            'visibility_fields' => $visibilityFields,
            'is_public' => $this->characterInputNormalizer->isPublic($visibilityFields),
            'owner_notes' => $new->getOwnerNotes(),
            'created_at' => $writtenAt,
            'updated_at' => $writtenAt,
        ];
    }
}
