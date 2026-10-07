<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Поля update, не порты DI. Space и владелец сюда не входят.

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Полная замена изменяемых полей игры.
 */
final class GamePatch
{
    /**
     * Создаёт patch.
     *
     * @param string $name Имя.
     * @param string $shortDescription Кратко.
     * @param string $description Текст.
     * @param string $status Статус.
     * @param string $visibility Видимость.
     * @param string $joinPolicy Политика входа.
     * @param int|null $osPointsLimit Потолок ОС.
     * @param int|null $olPointsLimit Потолок ОЛ.
     * @param int|null $orPointsLimit Потолок ОР.
     * @param int|null $moneyLimit Потолок денег.
     * @param int $rulesRevision Номер ревизии.
     *
     * @return void
     */
    private function __construct(
        private readonly string $name,
        private readonly string $shortDescription,
        private readonly string $description,
        private readonly string $status,
        private readonly string $visibility,
        private readonly string $joinPolicy,
        private readonly ?int $osPointsLimit,
        private readonly ?int $olPointsLimit,
        private readonly ?int $orPointsLimit,
        private readonly ?int $moneyLimit,
        private readonly int $rulesRevision,
    ) {
    }

    /**
     * Оборачивает набор свойств.
     *
     * @param array<string, mixed> $values Ключи домена.
     *
     * @return self Patch.
     *
     * @throws GameInvalidException Если типы неверны.
     */
    public static function fromNormalized(array $values): self
    {
        return new self(
            self::requireString($values['name'] ?? null),
            self::text($values['shortDescription'] ?? null),
            self::text($values['description'] ?? null),
            self::requireString($values['status'] ?? null),
            self::requireString($values['visibility'] ?? null),
            self::requireString($values['joinPolicy'] ?? null),
            self::requireNullableInt($values['osPointsLimit'] ?? null),
            self::requireNullableInt($values['olPointsLimit'] ?? null),
            self::requireNullableInt($values['orPointsLimit'] ?? null),
            self::requireNullableInt($values['moneyLimit'] ?? null),
            self::requireInt($values['rulesRevision'] ?? null),
        );
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
     * Номер ревизии.
     *
     * @return int Номер.
     */
    public function getRulesRevision(): int
    {
        return $this->rulesRevision;
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
            throw new GameInvalidException('Game field is invalid');
        }

        return $value;
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

    /**
     * Текст или пустая строка из null.
     *
     * @param mixed $value Вход.
     *
     * @return string Текст.
     *
     * @throws GameInvalidException Если не string и не null.
     */
    private static function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return self::requireString($value);
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
            throw new GameInvalidException('Game field is invalid');
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
}
