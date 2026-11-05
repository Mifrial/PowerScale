<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Метка, которую манёвр ставит не распознавшим обман.
 */
final class ManeuverMark
{
    /**
     * Создаёт описание метки.
     *
     * @param string $kind Вид метки.
     * @param int $value Знаковая дельта.
     * @param string $sourceCode Источник преимущества.
     * @param string $scope Область действия.
     * @param string $consumes Момент расхода.
     * @param string $expires Срок.
     *
     * @return void
     */
    public function __construct(
        private readonly string $kind,
        private readonly int $value,
        private readonly string $sourceCode,
        private readonly string $scope,
        private readonly string $consumes,
        private readonly string $expires,
    ) {
    }

    /**
     * Вид метки.
     *
     * @return string Код.
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Дельта.
     *
     * @return int Значение.
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * Источник.
     *
     * @return string Код.
     */
    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    /**
     * Область.
     *
     * @return string Код.
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * Момент расхода.
     *
     * @return string Код.
     */
    public function getConsumes(): string
    {
        return $this->consumes;
    }

    /**
     * Срок.
     *
     * @return string Код.
     */
    public function getExpires(): string
    {
        return $this->expires;
    }

    /**
     * JSON-форма для строки удара.
     *
     * @return array{kind: string, value: int, source_code: string, scope: string,
     *               consumes: string, expires: string} Метка.
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'value' => $this->value,
            'source_code' => $this->sourceCode,
            'scope' => $this->scope,
            'consumes' => $this->consumes,
            'expires' => $this->expires,
        ];
    }
}
