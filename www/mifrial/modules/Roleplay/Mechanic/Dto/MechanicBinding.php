<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

/**
 * Срез правила для Engine: код правила, id механики каталога и payload доплаты.
 */
final class MechanicBinding
{
    /**
     * Собирает binding.
     *
     * @param string $ruleCode Код правила.
     * @param int|null $mechanicId Id строки каталога; null — механики нет.
     * @param PurchaseSurchargePayload|null $mechanicPayload Payload доплаты или null.
     *
     * @return void
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly ?int $mechanicId,
        private readonly ?PurchaseSurchargePayload $mechanicPayload,
    ) {
    }

    /**
     * Код правила.
     *
     * @return string Код.
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * Id механики каталога.
     *
     * @return int|null Id или null.
     */
    public function getMechanicId(): ?int
    {
        return $this->mechanicId;
    }

    /**
     * Payload доплаты.
     *
     * @return PurchaseSurchargePayload|null Payload или null.
     */
    public function getMechanicPayload(): ?PurchaseSurchargePayload
    {
        return $this->mechanicPayload;
    }
}
