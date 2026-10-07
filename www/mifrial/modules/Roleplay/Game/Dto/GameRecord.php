<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Геттеры строки игры, не порты DI.

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Прочитанная строка игры.
 */
final class GameRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $id Id.
     * @param int $ownerId Владелец.
     * @param string $name Имя.
     * @param string $shortDescription Кратко.
     * @param string $description Текст.
     * @param string $status Статус.
     * @param string $visibility Видимость.
     * @param string $joinPolicy Политика входа.
     * @param int $spaceId Мир.
     * @param string $spaceCode Код мира.
     * @param int $rulesRevision Номер ревизии.
     * @param int|null $osPointsLimit Потолок ОС.
     * @param int|null $olPointsLimit Потолок ОЛ.
     * @param int|null $orPointsLimit Потолок ОР.
     * @param int|null $moneyLimit Потолок денег.
     * @param list<int> $whitelist Id учёток списка.
     * @param DateTime $createdAt Создание.
     * @param DateTime $updatedAt Обновление.
     * @param int|null $gameChatId Чат стола.
     * @param int|null $discussionChatId Чат обсуждения.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $ownerId,
        private readonly string $name,
        private readonly string $shortDescription,
        private readonly string $description,
        private readonly string $status,
        private readonly string $visibility,
        private readonly string $joinPolicy,
        private readonly int $spaceId,
        private readonly string $spaceCode,
        private readonly int $rulesRevision,
        private readonly ?int $osPointsLimit,
        private readonly ?int $olPointsLimit,
        private readonly ?int $orPointsLimit,
        private readonly ?int $moneyLimit,
        private readonly array $whitelist,
        private readonly DateTime $createdAt,
        private readonly DateTime $updatedAt,
        private readonly ?int $gameChatId,
        private readonly ?int $discussionChatId,
    ) {
    }

    /**
     * Собирает запись из строки SmartTable.
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
            self::requireInt($fields['owner_id'] ?? null),
            self::requireString($fields['name'] ?? null),
            self::requireString($fields['short_description'] ?? null),
            self::requireString($fields['description'] ?? null),
            self::requireString($fields['status'] ?? null),
            self::requireString($fields['visibility'] ?? null),
            self::requireString($fields['join_policy'] ?? null),
            self::requireInt($fields['space_id'] ?? null),
            self::requireString($fields['space_code'] ?? null),
            self::requireInt($fields['rules_revision'] ?? null),
            self::requireNullableInt($fields['os_points_limit'] ?? null),
            self::requireNullableInt($fields['ol_points_limit'] ?? null),
            self::requireNullableInt($fields['or_points_limit'] ?? null),
            self::requireNullableInt($fields['money_limit'] ?? null),
            self::requireIntList($fields['whitelist'] ?? null),
            self::requireDateTime($fields['created_at'] ?? null),
            self::requireDateTime($fields['updated_at'] ?? null),
            self::requireNullableInt($fields['game_chat_id'] ?? null),
            self::requireNullableInt($fields['discussion_chat_id'] ?? null),
        );
    }

    /**
     * Id игры.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Владелец.
     *
     * @return int Id учётки.
     */
    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    /**
     * Имя.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Краткое описание.
     *
     * @return string Текст.
     */
    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    /**
     * Полное описание.
     *
     * @return string Текст.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Статус кампании.
     *
     * @return string Код.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Кампания закрыта.
     *
     * @return bool true, если completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Видимость.
     *
     * @return string Код.
     */
    public function getVisibility(): string
    {
        return $this->visibility;
    }

    /**
     * Политика входа.
     *
     * @return string Код.
     */
    public function getJoinPolicy(): string
    {
        return $this->joinPolicy;
    }

    /**
     * Мир.
     *
     * @return int space id.
     */
    public function getSpaceId(): int
    {
        return $this->spaceId;
    }

    /**
     * Код мира на момент создания.
     *
     * @return string Код.
     */
    public function getSpaceCode(): string
    {
        return $this->spaceCode;
    }

    /**
     * Номер ревизии.
     *
     * @return int Номер.
     */
    public function getRulesRevision(): int
    {
        return $this->rulesRevision;
    }

    /**
     * Потолок ОС.
     *
     * @return int|null Число или нет потолка.
     */
    public function getOsPointsLimit(): ?int
    {
        return $this->osPointsLimit;
    }

    /**
     * Потолок ОЛ.
     *
     * @return int|null Число или нет потолка.
     */
    public function getOlPointsLimit(): ?int
    {
        return $this->olPointsLimit;
    }

    /**
     * Потолок ОР.
     *
     * @return int|null Число или нет потолка.
     */
    public function getOrPointsLimit(): ?int
    {
        return $this->orPointsLimit;
    }

    /**
     * Потолок денег.
     *
     * @return int|null Число или нет потолка.
     */
    public function getMoneyLimit(): ?int
    {
        return $this->moneyLimit;
    }

    /**
     * Id учёток списка. В JSON карточки не входит.
     *
     * @return list<int> Id.
     */
    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    /**
     * Создание.
     *
     * @return DateTime UTC.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Обновление.
     *
     * @return DateTime UTC.
     */
    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Чат стола.
     *
     * @return int|null Id или null.
     */
    public function getGameChatId(): ?int
    {
        return $this->gameChatId;
    }

    /**
     * Чат обсуждения.
     *
     * @return int|null Id или null.
     */
    public function getDiscussionChatId(): ?int
    {
        return $this->discussionChatId;
    }

    /**
     * Список id.
     *
     * @param mixed $value Вход.
     *
     * @return list<int> Id.
     *
     * @throws GameInvalidException Если не список int.
     */
    private static function requireIntList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new GameInvalidException('Game row is invalid');
        }

        $ids = [];
        foreach ($value as $item) {
            if (!is_int($item)) {
                throw new GameInvalidException('Game row is invalid');
            }

            $ids[] = $item;
        }

        return $ids;
    }

    /**
     * Целое.
     *
     * @param mixed $value Вход.
     *
     * @return int Число.
     *
     * @throws GameInvalidException Если не int.
     */
    private static function requireInt(mixed $value): int
    {
        if (!is_int($value)) {
            throw new GameInvalidException('Game row is invalid');
        }

        return $value;
    }

    /**
     * Строка.
     *
     * @param mixed $value Вход.
     *
     * @return string Текст.
     *
     * @throws GameInvalidException Если не string.
     */
    private static function requireString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new GameInvalidException('Game row is invalid');
        }

        return $value;
    }

    /**
     * Потолок или null.
     *
     * @param mixed $value Вход.
     *
     * @return int|null Число или null.
     *
     * @throws GameInvalidException Если не int и не null.
     */
    private static function requireNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return self::requireInt($value);
    }

    /**
     * Момент.
     *
     * @param mixed $value Вход.
     *
     * @return DateTime UTC.
     *
     * @throws GameInvalidException Если не DateTime.
     */
    private static function requireDateTime(mixed $value): DateTime
    {
        if (!$value instanceof DateTime) {
            throw new GameInvalidException('Game row is invalid');
        }

        return $value;
    }
}
