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
    private const INVALID = 'Game field is invalid';

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
            GameRowFields::string($values, 'name', self::INVALID),
            GameRowFields::text($values, 'shortDescription', self::INVALID),
            GameRowFields::text($values, 'description', self::INVALID),
            GameRowFields::string($values, 'status', self::INVALID),
            GameRowFields::string($values, 'visibility', self::INVALID),
            GameRowFields::string($values, 'joinPolicy', self::INVALID),
            GameRowFields::nullableInt($values, 'osPointsLimit', self::INVALID),
            GameRowFields::nullableInt($values, 'olPointsLimit', self::INVALID),
            GameRowFields::nullableInt($values, 'orPointsLimit', self::INVALID),
            GameRowFields::nullableInt($values, 'moneyLimit', self::INVALID),
            GameRowFields::int($values, 'rulesRevision', self::INVALID),
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
}
