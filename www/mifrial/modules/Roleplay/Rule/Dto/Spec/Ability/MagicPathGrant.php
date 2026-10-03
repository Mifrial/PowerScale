<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Грант пути.
 */
final class MagicPathGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $pathCode Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $pathCode,
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
        return 'magic_path';
    }

    /**
     * Код.
     *
     * @return string Значение.
     */
    public function getPathCode(): string
    {
        return $this->pathCode;
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
