<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;

/**
 * Проверенная сохранённая строка ресурса.
 */
final class ResourceRow
{
    /**
     * Создаёт строку ресурса.
     *
     * @param string $ruleCode Live resource rule code.
     * @param ResourceValue $current Authoritative current value.
     *
     * @return void
     *
     * @throws CharacterInvalidException If the code is empty.
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly ResourceValue $current,
    ) {
        if ($ruleCode === '') {
            throw new CharacterInvalidException('Resource rule code is empty');
        }
    }

    /**
     * Возвращает код ресурса.
     *
     * @return string Rule code.
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * Возвращает типизированное текущее значение.
     *
     * @return ResourceValue Current value.
     */
    public function getCurrent(): ResourceValue
    {
        return $this->current;
    }
}
