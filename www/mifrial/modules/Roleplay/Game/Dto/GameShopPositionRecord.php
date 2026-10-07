<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка позиции магазина.
 */
final class GameShopPositionRecord
{
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
        $sellPrice = $fields['sell_price'] ?? null;
        if ($sellPrice !== null && !is_int($sellPrice)) {
            throw new GameInvalidException('Shop sell price is invalid');
        }

        return new self(
            self::intOf($fields, 'id'),
            self::intOf($fields, 'game_id'),
            self::stringOf($fields, 'rule_code'),
            self::intOf($fields, 'buy_price'),
            $sellPrice,
            self::intOf($fields, 'quantity'),
            self::intOf($fields, 'version'),
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

    /**
     * Целое поле.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Колонка.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если не int.
     */
    private static function intOf(array $fields, string $key): int
    {
        $value = $fields[$key] ?? null;
        if (!is_int($value)) {
            throw new GameInvalidException('Shop field is invalid');
        }

        return $value;
    }

    /**
     * Строковое поле.
     *
     * @param array<string, mixed> $fields Строка.
     * @param string $key Колонка.
     *
     * @return string Значение.
     *
     * @throws GameInvalidException Если не строка.
     */
    private static function stringOf(array $fields, string $key): string
    {
        $value = $fields[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new GameInvalidException('Shop field is invalid');
        }

        return $value;
    }
}
