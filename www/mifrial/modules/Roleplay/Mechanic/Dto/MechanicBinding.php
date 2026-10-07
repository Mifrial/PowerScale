<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Срез правила для Engine: код правила, id механики каталога и payload.
 */
final class MechanicBinding
{
    /**
     * Собирает binding.
     *
     * @param string $ruleCode Код правила.
     * @param int|null $mechanicId Id строки каталога; null — механики нет.
     * @param MechanicPayload|null $mechanicPayload Payload механики или null.
     *
     * @return void
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly ?int $mechanicId,
        private readonly ?MechanicPayload $mechanicPayload,
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
     * Payload механики.
     *
     * @return MechanicPayload|null Payload или null.
     */
    public function getMechanicPayload(): ?MechanicPayload
    {
        return $this->mechanicPayload;
    }
}
