<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterOpening;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Enum\CharacterSheetSection;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Repository\CharacterVisibilityRepository;
use Mifrial\Roleplay\Character\Service\Read\CharacterSectionCodes;
use Mifrial\Roleplay\Character\Service\Read\CharacterViewerParser;

/**
 * Право на лист: владелец, character.view и секции. Единственный вызывающий репозиторий видимости.
 */
final class CharacterSheetAccess
{
    private const VIEW = 'character.view';

    /**
     * Собирает доступ.
     *
     * @param CharacterVisibilityRepository $visibilityRepository Строки.
     * @param CharacterSectionCodes $sectionCodes Коды.
     * @param CharacterViewerParser $viewerParser Зрители.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterVisibilityRepository $visibilityRepository,
        private readonly CharacterSectionCodes $sectionCodes,
        private readonly CharacterViewerParser $viewerParser,
    ) {
    }

    /**
     * Строки, которые актор может увидеть в списке.
     *
     * @param RequestActor $actor Актор.
     *
     * @return list<CharacterRecord> Строки.
     *
     * @throws CharacterInvalidException Если выборка битая.
     */
    public function visibleList(RequestActor $actor): array
    {
        $sharedCharacterIds = null;
        if ($actor->hasKey(self::VIEW)) {
            $sharedCharacterIds = $this->visibilityRepository->findViewerCharacterIds($actor->getUserId());
        }

        return $this->withKnownGrant(
            $actor,
            $this->visibilityRepository->findVisible($actor->getUserId(), $sharedCharacterIds),
        );
    }

    /**
     * Лист для актора или NotFound без утечки.
     *
     * @param RequestActor $actor Актор.
     * @param int $characterId Персонаж.
     *
     * @return CharacterOpening Доступ.
     *
     * @throws CharacterNotFoundException Если нет строки, права или секции.
     * @throws CharacterInvalidException Если строка битая.
     */
    public function open(RequestActor $actor, int $characterId): CharacterOpening
    {
        $record = $this->visibilityRepository->getById($characterId);
        if ($record->getOwnerId() === $actor->getUserId()) {
            return $this->ownerOpening($record);
        }

        return $this->strangerOpening($actor, $record);
    }

    /**
     * Пишет видимость, если актор — владелец.
     *
     * @param RequestActor $actor Актор.
     * @param int $characterId Персонаж.
     * @param mixed $visibilityFields Секции всем.
     * @param mixed $viewers Зрители.
     *
     * @return CharacterOpening Вид владельца.
     *
     * @throws CharacterNotFoundException Если чужой или нет user.
     * @throws CharacterInvalidException Если коды или зрители.
     */
    public function replaceVisibility(
        RequestActor $actor,
        int $characterId,
        mixed $visibilityFields,
        mixed $viewers,
    ): CharacterOpening {
        $this->owned($actor, $characterId);
        $fields = $this->sectionCodes->normalize($visibilityFields);
        $grants = $this->viewerParser->parse($viewers);
        $record = $this->visibilityRepository->replaceVisibility(
            $characterId,
            $fields,
            $fields !== [],
            $grants,
            DateTime::now(),
        );

        return $this->ownerOpening($record);
    }

    /**
     * Пишет заметки, если актор — владелец.
     *
     * @param RequestActor $actor Актор.
     * @param int $characterId Персонаж.
     * @param string $notes Текст.
     *
     * @return CharacterOpening Вид владельца.
     *
     * @throws CharacterNotFoundException Если чужой или нет строки.
     * @throws CharacterInvalidException Если поле.
     */
    public function replaceOwnerNotes(RequestActor $actor, int $characterId, string $notes): CharacterOpening
    {
        $this->owned($actor, $characterId);

        return $this->ownerOpening(
            $this->visibilityRepository->replaceOwnerNotes($characterId, $notes, DateTime::now()),
        );
    }

    /**
     * Полный грант владельца и его зрители.
     *
     * @param CharacterRecord $record Строка.
     *
     * @return CharacterOpening Доступ.
     *
     * @throws CharacterInvalidException Если зрители битые.
     */
    private function ownerOpening(CharacterRecord $record): CharacterOpening
    {
        return new CharacterOpening(
            $record,
            true,
            CharacterSheetSection::codes(),
            $this->visibilityRepository->findViewers($record->getId()),
        );
    }

    /**
     * Чужой лист: ключ и хотя бы одна секция.
     *
     * @param RequestActor $actor Актор.
     * @param CharacterRecord $record Строка.
     *
     * @return CharacterOpening Доступ.
     *
     * @throws CharacterNotFoundException Если нет ключа или секций.
     * @throws CharacterInvalidException Если fields зрителя битые.
     */
    private function strangerOpening(RequestActor $actor, CharacterRecord $record): CharacterOpening
    {
        if (!$actor->hasKey(self::VIEW)) {
            throw new CharacterNotFoundException();
        }

        $sections = $this->strangerSections($record, $actor->getUserId());
        if ($sections === []) {
            throw new CharacterNotFoundException();
        }

        return new CharacterOpening($record, false, $sections, []);
    }

    /**
     * Объединение секций «всем» и строки зрителя.
     *
     * @param CharacterRecord $record Строка.
     * @param int $userId Актор.
     *
     * @return list<string> Коды.
     *
     * @throws CharacterInvalidException Если fields битые.
     */
    private function strangerSections(CharacterRecord $record, int $userId): array
    {
        $viewerFields = $this->visibilityRepository->findViewerFields($record->getId(), $userId) ?? [];

        return $this->sectionCodes->keepKnown([
            ...$record->getVisibilityFields(),
            ...$viewerFields,
        ]);
    }

    /**
     * Убирает чужие строки, у которых после enum не осталось секций.
     *
     * @param RequestActor $actor Актор.
     * @param list<CharacterRecord> $records Выборка репозитория.
     *
     * @return list<CharacterRecord> Строки, которые open отдаст.
     *
     * @throws CharacterInvalidException Если fields зрителя битые.
     */
    private function withKnownGrant(RequestActor $actor, array $records): array
    {
        $visible = [];
        foreach ($records as $record) {
            if ($record->getOwnerId() === $actor->getUserId() || $this->strangerSections($record, $actor->getUserId()) !== []) {
                $visible[] = $record;
            }
        }

        return $visible;
    }

    /**
     * Строка только владельца.
     *
     * @param RequestActor $actor Актор.
     * @param int $characterId Персонаж.
     *
     * @return CharacterRecord Строка.
     *
     * @throws CharacterNotFoundException Если нет или владелец другой.
     * @throws CharacterInvalidException Если Record неполный.
     */
    private function owned(RequestActor $actor, int $characterId): CharacterRecord
    {
        $record = $this->visibilityRepository->getById($characterId);
        if ($record->getOwnerId() !== $actor->getUserId()) {
            throw new CharacterNotFoundException();
        }

        return $record;
    }
}
