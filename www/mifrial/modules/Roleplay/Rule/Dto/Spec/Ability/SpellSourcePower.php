<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;

/**
 * Бонус к потолку мощи источника каста.
 */
final class SpellSourcePower
{
    /**
     * Создаёт бонус.
     *
     * @param string $scopeKind Область: any, innate, item или source.
     * @param string|null $itemCode Код предмета для item.
     * @param string|null $targetCode Ключ цели в targets выбора для source.
     * @param bool $pathScoped Только при касте через путь.
     * @param ScalarFormula $amount Величина бонуса.
     *
     * @return void
     */
    public function __construct(
        private readonly string $scopeKind,
        private readonly ?string $itemCode,
        private readonly ?string $targetCode,
        private readonly bool $pathScoped,
        private readonly ScalarFormula $amount,
    ) {
    }

    /**
     * Область действия.
     *
     * @return string any, innate, item или source.
     */
    public function getScopeKind(): string
    {
        return $this->scopeKind;
    }

    /**
     * Код предмета области item.
     *
     * @return string|null Код или null.
     */
    public function getItemCode(): ?string
    {
        return $this->itemCode;
    }

    /**
     * Ключ цели области source.
     *
     * @return string|null Ключ или null.
     */
    public function getTargetCode(): ?string
    {
        return $this->targetCode;
    }

    /**
     * Бонус только при касте через путь.
     *
     * @return bool Значение.
     */
    public function isPathScoped(): bool
    {
        return $this->pathScoped;
    }

    /**
     * Величина бонуса.
     *
     * @return ScalarFormula Формула.
     */
    public function getAmount(): ScalarFormula
    {
        return $this->amount;
    }
}
