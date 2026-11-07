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
    private const INVALID = 'Game row is invalid';

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
            GameRowFields::int($fields, 'id', self::INVALID),
            GameRowFields::int($fields, 'owner_id', self::INVALID),
            GameRowFields::string($fields, 'name', self::INVALID),
            GameRowFields::string($fields, 'short_description', self::INVALID),
            GameRowFields::string($fields, 'description', self::INVALID),
            GameRowFields::string($fields, 'status', self::INVALID),
            GameRowFields::string($fields, 'visibility', self::INVALID),
            GameRowFields::string($fields, 'join_policy', self::INVALID),
            GameRowFields::int($fields, 'space_id', self::INVALID),
            GameRowFields::string($fields, 'space_code', self::INVALID),
            GameRowFields::int($fields, 'rules_revision', self::INVALID),
            GameRowFields::nullableInt($fields, 'os_points_limit', self::INVALID),
            GameRowFields::nullableInt($fields, 'ol_points_limit', self::INVALID),
            GameRowFields::nullableInt($fields, 'or_points_limit', self::INVALID),
            GameRowFields::nullableInt($fields, 'money_limit', self::INVALID),
            self::requireIntList($fields['whitelist'] ?? null),
            GameRowFields::dateTime($fields, 'created_at', self::INVALID),
            GameRowFields::dateTime($fields, 'updated_at', self::INVALID),
            GameRowFields::nullableInt($fields, 'game_chat_id', self::INVALID),
            GameRowFields::nullableInt($fields, 'discussion_chat_id', self::INVALID),
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
            throw new GameInvalidException(self::INVALID);
        }

        $ids = [];
        foreach ($value as $item) {
            if (!is_int($item)) {
                throw new GameInvalidException(self::INVALID);
            }

            $ids[] = $item;
        }

        return $ids;
    }








}
