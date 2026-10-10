<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

/**
 * Ожидаемый счётчик одной строки открытой карты.
 */
final class ConditionalCas
{
    /**
     * Создаёт условие CAS.
     *
     * @param string $casField Поле карты.
     * @param int $expectedValue Ожидаемое целое.
     *
     * @return void
     */
    public function __construct(
        public readonly string $casField,
        public readonly int $expectedValue,
    ) {
    }
}
