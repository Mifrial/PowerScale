<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Interface\Service\IGameSessionRoster;

/**
 * reviewState: returned хранится, clean и changes_pending считаются.
 */
final class GameCharacterReview
{
    /**
     * Создаёт вывод.
     *
     * @param GameCharacterDiff $diff Компаратор.
     * @param ICharacterSheets $sheets Проверка листа.
     * @param IGameSessionRoster $sessionRoster Состав сессии.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCharacterDiff $diff,
        private readonly ICharacterSheets $sheets,
        private readonly IGameSessionRoster $sessionRoster,
    ) {
    }

    /**
     * Код для ответа.
     *
     * @param array<string, mixed>|null $snapshot Копия или null.
     * @param DateTime|null $returnedAt Маркер return.
     * @param CharacterRecord $actual Текущий лист.
     *
     * @return string clean, changes_pending или returned.
     *
     * @throws GameInvalidException Если snapshot не сравнивается.
     */
    public function reviewState(?array $snapshot, ?DateTime $returnedAt, CharacterRecord $actual): string
    {
        if ($returnedAt !== null) {
            return 'returned';
        }

        if ($snapshot === null || $this->diff->hasChanges($snapshot, $actual)) {
            return 'changes_pending';
        }

        return 'clean';
    }

    /**
     * Нет snapshot или semantic diff. Return сюда не входит.
     *
     * @param array<string, mixed>|null $snapshot Копия или null.
     * @param CharacterRecord $actual Текущий лист.
     *
     * @return bool true, если модерация нужна.
     *
     * @throws GameInvalidException Если snapshot не сравнивается.
     */
    public function needsModeration(?array $snapshot, CharacterRecord $actual): bool
    {
        return $snapshot === null || $this->diff->hasChanges($snapshot, $actual);
    }

    /**
     * Допуск следующей сессии. Непройденный срез гасит допуск.
     *
     * @param GameRecord $game Игра.
     * @param GameCharacterRecord $row Строка.
     * @param CharacterRecord $actual Текущий лист.
     *
     * @return bool true, если персонаж может войти.
     *
     * @throws GameInvalidException Если snapshot не сравнивается.
     */
    public function canStartSession(GameRecord $game, GameCharacterRecord $row, CharacterRecord $actual): bool
    {
        if (!$this->rowAdmits($game, $row, $actual)) {
            return false;
        }

        try {
            return $this->sheets->acceptsStoredChoices(
                $actual->getSpaceId(),
                $actual->getRulesRevision(),
                $actual->getChoices(),
            );
        } catch (CharacterNotFoundException | CharacterInvalidException) {
            return false;
        }
    }

    /**
     * Уже в текущей сессии этой пары.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return bool true, если персонаж в составе.
     */
    public function isActiveSessionParticipant(int $gameId, int $characterId): bool
    {
        return $this->sessionRoster->isParticipant($gameId, $characterId);
    }

    /**
     * Строка и ревизия допускают сессию до проверки листа.
     *
     * @param GameRecord $game Игра.
     * @param GameCharacterRecord $row Строка.
     * @param CharacterRecord $actual Текущий лист.
     *
     * @return bool true, если статус, diff, return и ревизия сходятся.
     *
     * @throws GameInvalidException Если snapshot не сравнивается.
     */
    private function rowAdmits(GameRecord $game, GameCharacterRecord $row, CharacterRecord $actual): bool
    {
        $snapshot = $row->getApprovedCharacterVersion();
        $sameRevision = $actual->getRulesRevision() === $game->getRulesRevision();
        $clean = $snapshot !== null && !$this->diff->hasChanges($snapshot, $actual);

        return $row->getStatus() === 'active' && $row->getReturnedAt() === null && $clean && $sameRevision;
    }
}
