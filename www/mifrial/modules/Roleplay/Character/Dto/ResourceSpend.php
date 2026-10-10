<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;

/**
 * Подготовленная сервером native-трата ресурса.
 */
final class ResourceSpend
{
    /**
     * Создаёт подготовленную трату.
     *
     * @param string $resourceCode Код ресурса.
     * @param ResourceValue $amount Native-сумма.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если код пуст.
     */
    public function __construct(
        private readonly string $resourceCode,
        private readonly ResourceValue $amount,
    ) {
        if ($resourceCode === '') {
            throw new CharacterInvalidException('Resource spend code is empty');
        }
    }

    /**
     * Возвращает код ресурса.
     *
     * @return string Код.
     */
    public function getResourceCode(): string
    {
        return $this->resourceCode;
    }

    /**
     * Возвращает native-сумму.
     *
     * @return ResourceValue Сумма.
     */
    public function getAmount(): ResourceValue
    {
        return $this->amount;
    }
}
