<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;

/**
 * Грант изучения магии.
 */
final class MagicStudyGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $scope Поле.
     * @param ScalarFormula $maxCost Поле.
     * @param string $pathCode Поле.
     * @param ?int $maxInstances Поле.
     * @param ?int $paidCost Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $scope,
        private readonly ScalarFormula $maxCost,
        private readonly string $pathCode,
        private readonly ?int $maxInstances,
        private readonly ?int $paidCost,
        private readonly bool $permanent,
    ) {
    }

    /**
     * Тип гранта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'magic_study';
    }

    /**
     * spell или non_spell.
     *
     * @return string Значение.
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * Потолок цены.
     *
     * @return ScalarFormula Значение.
     */
    public function getMaxCost(): ScalarFormula
    {
        return $this->maxCost;
    }

    /**
     * Путь.
     *
     * @return string Значение.
     */
    public function getPathCode(): string
    {
        return $this->pathCode;
    }

    /**
     * Лимит экземпляров.
     *
     * @return ?int Значение.
     */
    public function getMaxInstances(): ?int
    {
        return $this->maxInstances;
    }

    /**
     * Оплата.
     *
     * @return ?int Значение.
     */
    public function getPaidCost(): ?int
    {
        return $this->paidCost;
    }

    /**
     * Постоянный грант.
     *
     * @return bool Значение.
     */
    public function isPermanent(): bool
    {
        return $this->permanent;
    }
}
