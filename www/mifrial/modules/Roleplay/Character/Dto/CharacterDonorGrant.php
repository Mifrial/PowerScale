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
     * @param int $blockLevel Уровень блока гранта.
     *
     * @return void
     */
    public function __construct(
        private readonly string $donorCode,
        private readonly AbilityGrant $grant,
        private readonly int $blockLevel = 0,
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

    /**
     * Уровень блока гранта.
     *
     * @return int Уровень.
     */
    public function getBlockLevel(): int
    {
        return $this->blockLevel;
    }
}
