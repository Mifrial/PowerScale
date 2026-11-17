<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Геттеры строки — публичный смысл Record; параметры ctor — колонки, не порты.

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка наложенного длительного эффекта боя.
 */
final class GameActiveEffectRecord
{
    private const INVALID = 'Game active effect row is invalid';

    /**
     * Создаёт запись.
     *
     * @param int $id Id строки.
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $sourceType Вид источника.
     * @param string $sourceRuleCode Код карточки источника.
     * @param string|null $applierKind character, npc или null.
     * @param int|null $applierId Id наложившего или null.
     * @param string $targetKind character или npc.
     * @param int $targetId Id цели.
     * @param string $durationType Вид длительности.
     * @param string|null $durationUnit Единица срока.
     * @param int|null $turnsTotal Срок в тиках или null.
     * @param int|null $turnsLeft Остаток тиков или null.
     * @param array{base: int, size: int}|null $strength Сила.
     * @param array<string, mixed> $context Контекст.
     * @param int $startedRound Раунд наложения.
     * @param string $startedParticipant Участник наложения.
     * @param int $stateVersion Версия строки.
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly int $gameId,
        private readonly int $sessionId,
        private readonly int $battleId,
        private readonly string $sourceType,
        private readonly string $sourceRuleCode,
        private readonly ?string $applierKind,
        private readonly ?int $applierId,
        private readonly string $targetKind,
        private readonly int $targetId,
        private readonly string $durationType,
        private readonly ?string $durationUnit,
        private readonly ?int $turnsTotal,
        private readonly ?int $turnsLeft,
        private readonly ?array $strength,
        private readonly array $context,
        private readonly int $startedRound,
        private readonly string $startedParticipant,
        private readonly int $stateVersion,
    ) {
    }

    /**
     * Запись из строки таблицы.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return self Строка.
     *
     * @throws GameInvalidException Если поле битое.
     */
    public static function fromNormalized(array $fields): self
    {
        return new self(
            GameRowFields::int($fields, 'id', self::INVALID),
            GameRowFields::int($fields, 'game_id', self::INVALID),
            GameRowFields::int($fields, 'session_id', self::INVALID),
            GameRowFields::int($fields, 'battle_id', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'source_type', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'source_rule_code', self::INVALID),
            GameRowFields::nullableString($fields, 'applier_kind', self::INVALID),
            GameRowFields::nullableInt($fields, 'applier_id', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'target_kind', self::INVALID),
            GameRowFields::int($fields, 'target_id', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'duration_type', self::INVALID),
            GameRowFields::nullableString($fields, 'duration_unit', self::INVALID),
            GameRowFields::nullableInt($fields, 'turns_total', self::INVALID),
            GameRowFields::nullableInt($fields, 'turns_left', self::INVALID),
            self::strength($fields, 'strength'),
            self::context($fields, 'context'),
            GameRowFields::int($fields, 'started_round', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'started_participant', self::INVALID),
            GameRowFields::int($fields, 'state_version', self::INVALID),
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
     * @return int Id игры.
     */
    public function getGameId(): int
    {
        return $this->gameId;
    }

    /**
     * Сессия.
     *
     * @return int Id сессии.
     */
    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    /**
     * Бой.
     *
     * @return int Id боя.
     */
    public function getBattleId(): int
    {
        return $this->battleId;
    }

    /**
     * Вид источника.
     *
     * @return string Код.
     */
    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    /**
     * Карточка источника.
     *
     * @return string Код.
     */
    public function getSourceRuleCode(): string
    {
        return $this->sourceRuleCode;
    }

    /**
     * Вид наложившего.
     *
     * @return string|null Код или null.
     */
    public function getApplierKind(): ?string
    {
        return $this->applierKind;
    }

    /**
     * Id наложившего.
     *
     * @return int|null Id или null.
     */
    public function getApplierId(): ?int
    {
        return $this->applierId;
    }

    /**
     * Ключ наложившего.
     *
     * @return string|null character:1 или null.
     */
    public function getApplierKey(): ?string
    {
        if ($this->applierKind === null || $this->applierId === null) {
            return null;
        }

        return $this->applierKind . ':' . $this->applierId;
    }

    /**
     * Вид цели.
     *
     * @return string character или npc.
     */
    public function getTargetKind(): string
    {
        return $this->targetKind;
    }

    /**
     * Id цели.
     *
     * @return int Id.
     */
    public function getTargetId(): int
    {
        return $this->targetId;
    }

    /**
     * Ключ цели.
     *
     * @return string character:1 или npc:1.
     */
    public function getTargetKey(): string
    {
        return $this->targetKind . ':' . $this->targetId;
    }

    /**
     * Вид длительности.
     *
     * @return string Код.
     */
    public function getDurationType(): string
    {
        return $this->durationType;
    }

    /**
     * Единица срока.
     *
     * @return string|null Код или null.
     */
    public function getDurationUnit(): ?string
    {
        return $this->durationUnit;
    }

    /**
     * Срок в тиках.
     *
     * @return int|null Тики или null.
     */
    public function getTurnsTotal(): ?int
    {
        return $this->turnsTotal;
    }

    /**
     * Остаток тиков.
     *
     * @return int|null Тики или null.
     */
    public function getTurnsLeft(): ?int
    {
        return $this->turnsLeft;
    }

    /**
     * Сила эффекта.
     *
     * @return array{base: int, size: int}|null Пара или null.
     */
    public function getStrength(): ?array
    {
        return $this->strength;
    }

    /**
     * Контекст источника.
     *
     * @return array<string, mixed> Контекст.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Раунд наложения.
     *
     * @return int Раунд.
     */
    public function getStartedRound(): int
    {
        return $this->startedRound;
    }

    /**
     * Участник наложения.
     *
     * @return string Ключ.
     */
    public function getStartedParticipant(): string
    {
        return $this->startedParticipant;
    }

    /**
     * Версия строки.
     *
     * @return int Версия.
     */
    public function getStateVersion(): int
    {
        return $this->stateVersion;
    }

    /**
     * Необязательная размерная пара.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     *
     * @return array{base: int, size: int}|null Пара или null.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private static function strength(array $fields, string $key): ?array
    {
        $value = $fields[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (!is_array($value) || array_is_list($value) || count($value) !== 2) {
            throw new GameInvalidException(self::INVALID);
        }

        $base = $value['base'] ?? null;
        $size = $value['size'] ?? null;
        if (!is_int($base) || !is_int($size)) {
            throw new GameInvalidException(self::INVALID);
        }

        return ['base' => $base, 'size' => $size];
    }

    /**
     * Карта контекста.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private static function context(array $fields, string $key): array
    {
        $value = $fields[$key] ?? [];
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new GameInvalidException(self::INVALID);
        }

        return $value;
    }
}
