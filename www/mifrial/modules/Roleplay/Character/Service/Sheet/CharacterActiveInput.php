<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterProblemList;

/**
 * Поле active во входе: нет ключа — true, не bool — отказ.
 */
final class CharacterActiveInput
{
    /**
     * Читает active.
     *
     * @param array<string, mixed> $input Тело.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return bool Признак листа.
     */
    public function read(array $input, CharacterProblemList $problems): bool
    {
        if (!array_key_exists('active', $input)) {
            return true;
        }

        if ($input['active'] === true || $input['active'] === false) {
            return $input['active'];
        }

        $problems->add('CHARACTER_ACTIVE', 'Active must be a boolean', 'input', 'active');

        return true;
    }
}
