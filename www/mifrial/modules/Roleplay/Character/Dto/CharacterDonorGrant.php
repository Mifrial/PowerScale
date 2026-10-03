<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityGrant;

/**
 * Грант выбранной способности вместе с кодом донора.
 */
final class CharacterDonorGrant
{
    /**
     * Создаёт пару.
     *
     * @param string $donorCode Код способности-донора.
     * @param AbilityGrant $grant Грант правила.
     *
     * @return void
     */
    public function __construct(
        private readonly string $donorCode,
        private readonly AbilityGrant $grant,
    ) {
    }

    /**
     * Код донора.
     *
     * @return string Код.
     */
    public function getDonorCode(): string
    {
        return $this->donorCode;
    }

    /**
     * Грант правила.
     *
     * @return AbilityGrant Грант.
     */
    public function getGrant(): AbilityGrant
    {
        return $this->grant;
    }
}
