<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Семь колонок строки магазина — поля строки, не порты DI.

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка позиции магазина.
 */
final class GameShopPositionRecord
{
    private const INVALID = 'Shop field is invalid';

    private const SELL_INVALID = 'Shop sell price is invalid';

    /**
     * Создаёт запись.
     *
     * @param int $id Id.
     * @param int $gameId Игра.
     * @param string $ruleCode Код предмета.
     * @param int $buyPrice Цена покупки.
     * @param int|null $sellPrice Цена выкупа или null.
     * @param int $quantity Остаток.
     * @param int $version CAS.
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly int $gameId,
        private readonly string $ruleCode,
        private readonly int $buyPrice,
        private readonly ?int $sellPrice,
        private readonly int $quantity,
        private readonly int $version,
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
        $sellPrice = GameRowFields::nullableInt($fields, 'sell_price', self::SELL_INVALID);

        return new self(
            GameRowFields::int($fields, 'id', self::INVALID),
            GameRowFields::int($fields, 'game_id', self::INVALID),
            GameRowFields::nonEmptyString($fields, 'rule_code', self::INVALID),
            GameRowFields::int($fields, 'buy_price', self::INVALID),
            $sellPrice,
            GameRowFields::int($fields, 'quantity', self::INVALID),
            GameRowFields::int($fields, 'version', self::INVALID),
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
     * Код предмета.
     *
     * @return string Код.
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * Цена покупки.
     *
     * @return int Сумма.
     */
    public function getBuyPrice(): int
    {
        return $this->buyPrice;
    }

    /**
     * Цена выкупа.
     *
     * @return int|null Сумма или null.
     */
    public function getSellPrice(): ?int
    {
        return $this->sellPrice;
    }

    /**
     * Остаток.
     *
     * @return int Количество.
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Версия строки.
     *
     * @return int CAS.
     */
    public function getVersion(): int
    {
        return $this->version;
    }




}
