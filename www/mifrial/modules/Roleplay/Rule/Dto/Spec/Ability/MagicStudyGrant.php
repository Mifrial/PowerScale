<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;

/**
 * Грант изучения карточек в рамках пути.
 */
final class MagicStudyGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param StudyPaths $paths Пути, на которых открыто изучение.
     * @param ScalarFormula $maxCost Потолок цены первого уровня.
     * @param ?int $maxInstances Лимит экземпляров на путь.
     * @param ?int $paidCost Цена экземпляра для редактора.
     * @param bool $permanent Постоянный грант.
     * @param string $scope Прежнее поле ревизий до 46.
     * @param string $pathCode Прежнее одиночное поле путей.
     *
     * @return void
     */
    public function __construct(
        private readonly StudyPaths $paths,
        private readonly ScalarFormula $maxCost,
        private readonly ?int $maxInstances,
        private readonly ?int $paidCost,
        private readonly bool $permanent,
        private readonly string $scope = '',
        private readonly string $pathCode = '',
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
     * Пути изучения.
     *
     * @return StudyPaths Набор.
     */
    public function getPaths(): StudyPaths
    {
        return $this->paths;
    }

    /**
     * Потолок цены первого уровня.
     *
     * @return ScalarFormula Значение.
     */
    public function getMaxCost(): ScalarFormula
    {
        return $this->maxCost;
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

    /**
     * Прежнее поле scope ревизий до 46.
     *
     * @return string Значение.
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * Прежнее одиночное поле пути ревизий до 46.
     *
     * @return string Значение.
     */
    public function getPathCode(): string
    {
        return $this->pathCode;
    }
}
