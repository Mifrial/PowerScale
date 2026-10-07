<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Геттеры и ctor — поля строки, не порты.

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка персонажа в игре. reviewState есть только после чтения actual.
 */
final class GameCharacterRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $id Id строки.
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $characterOwnerId Владелец на submit.
     * @param string $status Статус.
     * @param array<string, mixed>|null $approvedCharacterVersion Копия или null.
     * @param int $membershipRevision CAS.
     * @param DateTime|null $returnedAt Момент return.
     * @param string|null $returnReason Причина.
     * @param int|null $discussionChatId Чат строки.
     * @param int|null $returnMessageId Сообщение return.
     * @param int $osBonus Бонус ОС.
     * @param int $orBonus Бонус ОР.
     * @param int $olBonus Бонус ОЛ.
     * @param array<int, string> $sectionVisibility Коды секций.
     * @param string|null $reviewState clean, changes_pending, returned или null.
     * @param bool|null $needsModeration Нет snapshot или diff.
     * @param bool|null $canStartSession Допуск следующей сессии.
     * @param bool|null $isActiveSessionParticipant Уже в текущей сессии.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $gameId,
        private readonly int $characterId,
        private readonly int $characterOwnerId,
        private readonly string $status,
        private readonly ?array $approvedCharacterVersion,
        private readonly int $membershipRevision,
        private readonly ?DateTime $returnedAt,
        private readonly ?string $returnReason,
        private readonly ?int $discussionChatId,
        private readonly ?int $returnMessageId,
        private readonly int $osBonus,
        private readonly int $orBonus,
        private readonly int $olBonus,
        private readonly array $sectionVisibility,
        private readonly ?string $reviewState,
        private readonly ?bool $needsModeration,
        private readonly ?bool $canStartSession,
        private readonly ?bool $isActiveSessionParticipant,
    ) {
    }

    /**
     * Собирает запись из строки SmartTable. reviewState ещё нет.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return self Запись.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public static function fromNormalized(array $fields): self
    {
        return new self(
            self::requireInt($fields['id'] ?? null),
            self::requireInt($fields['game_id'] ?? null),
            self::requireInt($fields['character_id'] ?? null),
            self::requireInt($fields['character_owner_id'] ?? null),
            self::requireString($fields['status'] ?? null),
            self::requireSnapshot($fields['approved_character_version'] ?? null),
            self::requireInt($fields['membership_revision'] ?? null),
            self::requireNullableDateTime($fields['returned_at'] ?? null),
            self::requireNullableString($fields['return_reason'] ?? null),
            self::requireNullableInt($fields['discussion_chat_id'] ?? null),
            self::requireNullableInt($fields['return_message_id'] ?? null),
            self::requireInt($fields['os_bonus'] ?? null),
            self::requireInt($fields['or_bonus'] ?? null),
            self::requireInt($fields['ol_bonus'] ?? null),
            self::requireStringList($fields['section_visibility'] ?? null),
            null,
            null,
            null,
            null,
        );
    }

    /**
     * Та же строка с посчитанным reviewState.
     *
     * @param string $reviewState Код clean, changes_pending или returned.
     *
     * @return self Копия.
     */
    public function withReviewState(string $reviewState): self
    {
        return new self(
            $this->id,
            $this->gameId,
            $this->characterId,
            $this->characterOwnerId,
            $this->status,
            $this->approvedCharacterVersion,
            $this->membershipRevision,
            $this->returnedAt,
            $this->returnReason,
            $this->discussionChatId,
            $this->returnMessageId,
            $this->osBonus,
            $this->orBonus,
            $this->olBonus,
            $this->sectionVisibility,
            $reviewState,
            null,
            null,
            null,
        );
    }

    /**
     * Та же строка с посчитанным допуском.
     *
     * @param bool $needsModeration Нет snapshot или diff.
     * @param bool $canStartSession Допуск следующей сессии.
     * @param bool $isActiveSessionParticipant Уже в текущей сессии.
     *
     * @return self Копия.
     */
    public function withAdmission(
        bool $needsModeration,
        bool $canStartSession,
        bool $isActiveSessionParticipant,
    ): self {
        return new self(
            $this->id,
            $this->gameId,
            $this->characterId,
            $this->characterOwnerId,
            $this->status,
            $this->approvedCharacterVersion,
            $this->membershipRevision,
            $this->returnedAt,
            $this->returnReason,
            $this->discussionChatId,
            $this->returnMessageId,
            $this->osBonus,
            $this->orBonus,
            $this->olBonus,
            $this->sectionVisibility,
            $this->reviewState,
            $needsModeration,
            $canStartSession,
            $isActiveSessionParticipant,
        );
    }

    /**
     * Id строки.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Игра.
     *
     * @return int Id.
     */
    public function getGameId(): int
    {
        return $this->gameId;
    }

    /**
     * Персонаж.
     *
     * @return int Id.
     */
    public function getCharacterId(): int
    {
        return $this->characterId;
    }

    /**
     * Владелец на момент submit.
     *
     * @return int Id учётки.
     */
    public function getCharacterOwnerId(): int
    {
        return $this->characterOwnerId;
    }

    /**
     * Статус.
     *
     * @return string submitted, active или left.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Копия листа или null.
     *
     * @return array<string, mixed>|null Snapshot.
     */
    public function getApprovedCharacterVersion(): ?array
    {
        return $this->approvedCharacterVersion;
    }

    /**
     * Счётчик оптимистичной блокировки строки.
     *
     * @return int Revision.
     */
    public function getMembershipRevision(): int
    {
        return $this->membershipRevision;
    }

    /**
     * Момент return.
     *
     * @return DateTime|null Метка или null.
     */
    public function getReturnedAt(): ?DateTime
    {
        return $this->returnedAt;
    }

    /**
     * Причина return.
     *
     * @return string|null Текст или null.
     */
    public function getReturnReason(): ?string
    {
        return $this->returnReason;
    }

    /**
     * Чат строки.
     *
     * @return int|null Id или null.
     */
    public function getDiscussionChatId(): ?int
    {
        return $this->discussionChatId;
    }

    /**
     * Сообщение return.
     *
     * @return int|null Id или null.
     */
    public function getReturnMessageId(): ?int
    {
        return $this->returnMessageId;
    }

    /**
     * Бонус ОС.
     *
     * @return int Число.
     */
    public function getOsBonus(): int
    {
        return $this->osBonus;
    }

    /**
     * Бонус ОР.
     *
     * @return int Число.
     */
    public function getOrBonus(): int
    {
        return $this->orBonus;
    }

    /**
     * Бонус ОЛ.
     *
     * @return int Число.
     */
    public function getOlBonus(): int
    {
        return $this->olBonus;
    }

    /**
     * Коды секций в игре.
     *
     * @return array<int, string> Коды.
     */
    public function getSectionVisibility(): array
    {
        return $this->sectionVisibility;
    }

    /**
     * Состояние проверки после чтения actual.
     *
     * @return string|null Код или null, если actual не читали.
     */
    public function getReviewState(): ?string
    {
        return $this->reviewState;
    }

    /**
     * Нет snapshot или semantic diff.
     *
     * @return bool|null Флаг или null, если actual не читали.
     */
    public function needsModeration(): ?bool
    {
        return $this->needsModeration;
    }

    /**
     * Можно войти в следующую сессию.
     *
     * @return bool|null Флаг или null, если actual не читали.
     */
    public function canStartSession(): ?bool
    {
        return $this->canStartSession;
    }

    /**
     * Уже в текущей сессии. Пока сессии нет — false после чтения actual.
     *
     * @return bool|null Флаг или null, если actual не читали.
     */
    public function isActiveSessionParticipant(): ?bool
    {
        return $this->isActiveSessionParticipant;
    }

    /**
     * Целое.
     *
     * @param mixed $value Значение.
     *
     * @return int Число.
     *
     * @throws GameInvalidException Если не int.
     */
    private static function requireInt(mixed $value): int
    {
        if (!is_int($value)) {
            throw new GameInvalidException('Game character row is invalid');
        }

        return $value;
    }

    /**
     * Строка.
     *
     * @param mixed $value Значение.
     *
     * @return string Текст.
     *
     * @throws GameInvalidException Если не string.
     */
    private static function requireString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new GameInvalidException('Game character row is invalid');
        }

        return $value;
    }

    /**
     * Целое или null.
     *
     * @param mixed $value Значение.
     *
     * @return int|null Число.
     *
     * @throws GameInvalidException Если тип не тот.
     */
    private static function requireNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return self::requireInt($value);
    }

    /**
     * Строка или null.
     *
     * @param mixed $value Значение.
     *
     * @return string|null Текст.
     *
     * @throws GameInvalidException Если тип не тот.
     */
    private static function requireNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::requireString($value);
    }

    /**
     * Момент или null.
     *
     * @param mixed $value Значение.
     *
     * @return DateTime|null Момент.
     *
     * @throws GameInvalidException Если тип не тот.
     */
    private static function requireNullableDateTime(mixed $value): ?DateTime
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTime) {
            throw new GameInvalidException('Game character row is invalid');
        }

        return $value;
    }

    /**
     * Snapshot или null.
     *
     * @param mixed $value Значение.
     *
     * @return array<string, mixed>|null Копия.
     *
     * @throws GameInvalidException Если тип не тот.
     */
    private static function requireSnapshot(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new GameInvalidException('Game character row is invalid');
        }

        return $value;
    }

    /**
     * Список кодов.
     *
     * @param mixed $value Значение.
     *
     * @return array<int, string> Коды.
     *
     * @throws GameInvalidException Если тип не тот.
     */
    private static function requireStringList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new GameInvalidException('Game character row is invalid');
        }

        $codes = [];
        foreach ($value as $code) {
            if (!is_string($code)) {
                throw new GameInvalidException('Game character row is invalid');
            }

            $codes[] = $code;
        }

        return $codes;
    }
}
