<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Spec ресурса.
 */
final class ResourceSpec implements RuleSpec
{
    /**
     * Создаёт значение.
     *
     * @param bool $dimensional Размерный ресурс.
     * @param bool $autoAdd Автодобавление.
     * @param bool $checkToken Жетон проверки.
     * @param ?ResourceLimit $limit Лимит.
     * @param bool $turnRefill Восстановление на конце хода.
     *
     * @return void
     */
    public function __construct(
        private readonly bool $dimensional,
        private readonly bool $autoAdd,
        private readonly bool $checkToken,
        private readonly ?ResourceLimit $limit,
        private readonly bool $turnRefill = false,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'resource';
    }

    /**
     * Размерный ресурс.
     *
     * @return bool Значение.
     */
    public function isDimensional(): bool
    {
        return $this->dimensional;
    }

    /**
     * Автодобавление.
     *
     * @return bool Значение.
     */
    public function isAutoAdd(): bool
    {
        return $this->autoAdd;
    }

    /**
     * Жетон проверки.
     *
     * @return bool Значение.
     */
    public function isCheckToken(): bool
    {
        return $this->checkToken;
    }

    /**
     * Лимит.
     *
     * @return ?ResourceLimit Значение.
     */
    public function getLimit(): ?ResourceLimit
    {
        return $this->limit;
    }

    /**
     * Восстановление current на конце хода.
     *
     * @return bool true, если карточка refill.
     */
    public function isTurnRefill(): bool
    {
        return $this->turnRefill;
    }
}
