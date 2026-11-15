<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Геттеры строки — публичный смысл Record; параметры ctor — колонки, не порты.

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка поддерживаемого заклинания боя.
 */
final class GameActiveSpellRecord
{
    private const INVALID = 'Game active spell row is invalid';

    /**
     * Создаёт запись.
     *
     * @param int $id Id строки.
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $casterKind character или npc.
     * @param int $casterId Id кастера.
     * @param string $spellCode Код заклинания.
     * @param string $pathCode Код пути.
     * @param string $sourceKey Ключ источника.
     * @param string $durationType Вид длительности.
     * @param array{base: int, size: int} $usedPower Мощь сотворения.
     * @param array{base: int, size: int} $sustainPower Текущая мощь поддержания.
     * @param array<string, int|array{base: int, size: int}> $parameterValues Параметры.
     * @param array<int, string> $appliedUpgradeCodes Улучшения.
     * @param int $startedRound Раунд создания.
     * @param string $startedParticipant Участник создания.
     * @param int $casterSheetVersion Версия листа после каста.
     * @param int $stateVersion Версия строки.
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly int $gameId,
        private readonly int $sessionId,
        private readonly int $battleId,
        private readonly string $casterKind,
        private readonly int $casterId,
        private readonly string $spellCode,
        private readonly string $pathCode,
        private readonly string $sourceKey,
        private readonly string $durationType,
        private readonly array $usedPower,
        private readonly array $sustainPower,
        private readonly array $parameterValues,
        private readonly array $appliedUpgradeCodes,
        private readonly int $startedRound,
        private readonly string $startedParticipant,
        private readonly int $casterSheetVersion,
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
            GameRowFields::string($fields, 'caster_kind', self::INVALID),
            GameRowFields::int($fields, 'caster_id', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'spell_code', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'path_code', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'source_key', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'duration_type', self::INVALID),
            self::pair($fields, 'used_power'),
            self::pair($fields, 'sustain_power'),
            self::parameters($fields, 'parameter_values'),
            self::codes($fields, 'applied_upgrade_codes'),
            GameRowFields::int($fields, 'started_round', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'started_participant', self::INVALID),
            GameRowFields::int($fields, 'caster_sheet_version', self::INVALID),
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
     * Вид кастера.
     *
     * @return string character или npc.
     */
    public function getCasterKind(): string
    {
        return $this->casterKind;
    }

    /**
     * Id кастера.
     *
     * @return int Id.
     */
    public function getCasterId(): int
    {
        return $this->casterId;
    }

    /**
     * Ключ кастера.
     *
     * @return string character:1 или npc:1.
     */
    public function getCasterKey(): string
    {
        return $this->casterKind . ':' . $this->casterId;
    }

    /**
     * Код заклинания.
     *
     * @return string Код.
     */
    public function getSpellCode(): string
    {
        return $this->spellCode;
    }

    /**
     * Код пути.
     *
     * @return string Код.
     */
    public function getPathCode(): string
    {
        return $this->pathCode;
    }

    /**
     * Ключ источника.
     *
     * @return string Ключ.
     */
    public function getSourceKey(): string
    {
        return $this->sourceKey;
    }

    /**
     * Вид длительности.
     *
     * @return string Вид.
     */
    public function getDurationType(): string
    {
        return $this->durationType;
    }

    /**
     * Мощь сотворения.
     *
     * @return array{base: int, size: int} Пара.
     */
    public function getUsedPower(): array
    {
        return $this->usedPower;
    }

    /**
     * Мощь поддержания.
     *
     * @return array{base: int, size: int} Пара.
     */
    public function getSustainPower(): array
    {
        return $this->sustainPower;
    }

    /**
     * Активационные параметры.
     *
     * @return array<string, int|array{base: int, size: int}> Параметры.
     */
    public function getParameterValues(): array
    {
        return $this->parameterValues;
    }

    /**
     * Применённые улучшения.
     *
     * @return array<int, string> Коды.
     */
    public function getAppliedUpgradeCodes(): array
    {
        return $this->appliedUpgradeCodes;
    }

    /**
     * Раунд создания.
     *
     * @return int Раунд.
     */
    public function getStartedRound(): int
    {
        return $this->startedRound;
    }

    /**
     * Участник создания.
     *
     * @return string Ключ.
     */
    public function getStartedParticipant(): string
    {
        return $this->startedParticipant;
    }

    /**
     * Версия листа после каста.
     *
     * @return int Версия.
     */
    public function getCasterSheetVersion(): int
    {
        return $this->casterSheetVersion;
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
     * Размерная пара колонки.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     *
     * @return array{base: int, size: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private static function pair(array $fields, string $key): array
    {
        $value = $fields[$key] ?? null;
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
     * Карта активационных параметров.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     *
     * @return array<string, int|array{base: int, size: int}> Карта.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private static function parameters(array $fields, string $key): array
    {
        $value = $fields[$key] ?? [];
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new GameInvalidException(self::INVALID);
        }

        $parameters = [];
        foreach ($value as $code => $entry) {
            if (!is_string($code) || $code === '') {
                throw new GameInvalidException(self::INVALID);
            }

            $parameters[$code] = is_int($entry) ? $entry : self::entry($entry);
        }

        return $parameters;
    }

    /**
     * Значение параметра-пары.
     *
     * @param mixed $entry Значение.
     *
     * @return array{base: int, size: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private static function entry(mixed $entry): array
    {
        if (!is_array($entry) || count($entry) !== 2) {
            throw new GameInvalidException(self::INVALID);
        }

        $base = $entry['base'] ?? null;
        $size = $entry['size'] ?? null;
        if (!is_int($base) || !is_int($size)) {
            throw new GameInvalidException(self::INVALID);
        }

        return ['base' => $base, 'size' => $size];
    }

    /**
     * Список кодов улучшений.
     *
     * @param array<string, mixed> $fields Колонки.
     * @param string $key Колонка.
     *
     * @return array<int, string> Коды.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private static function codes(array $fields, string $key): array
    {
        $value = $fields[$key] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            throw new GameInvalidException(self::INVALID);
        }

        $codes = [];
        foreach ($value as $code) {
            if (!is_string($code) || $code === '') {
                throw new GameInvalidException(self::INVALID);
            }

            $codes[] = $code;
        }

        return $codes;
    }
}
