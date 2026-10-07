<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Параметры доплаты за покупку способностей: фильтр, бесплатный порог, размер доплаты.
 */
final class PurchaseSurchargePayload implements MechanicPayload
{
    /**
     * Собирает payload доплаты.
     *
     * @param string|null $keywordCode Код признака; null — фильтр по признаку выключен.
     * @param string|null $raceCode Код расы; null — фильтр по расе выключен. Со способностями не сравнивается.
     * @param int $freeCount Сколько первых совпадений без доплаты.
     * @param int $surcharge Размер доплаты за каждое следующее совпадение, в ОС.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $keywordCode,
        private readonly ?string $raceCode,
        private readonly int $freeCount,
        private readonly int $surcharge,
    ) {
    }

    /**
     * Код признака фильтра.
     *
     * @return string|null Код или null, если фильтр выключен.
     */
    public function getKeywordCode(): ?string
    {
        return $this->keywordCode;
    }

    /**
     * Код расы фильтра.
     *
     * @return string|null Код или null, если фильтр выключен.
     */
    public function getRaceCode(): ?string
    {
        return $this->raceCode;
    }

    /**
     * Бесплатный порог.
     *
     * @return int Число первых совпадений без доплаты.
     */
    public function getFreeCount(): int
    {
        return $this->freeCount;
    }

    /**
     * Размер доплаты.
     *
     * @return int ОС за каждое совпадение сверх порога.
     */
    public function getSurcharge(): int
    {
        return $this->surcharge;
    }
}
