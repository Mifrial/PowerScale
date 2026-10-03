<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Enum;

/**
 * Коды секций листа. Имя секции не является.
 */
enum CharacterSheetSection: string
{
    case ShortDescription = 'shortDescription';
    case FullDescription = 'fullDescription';
    case Race = 'race';
    case States = 'states';
    case Characteristics = 'characteristics';
    case Resources = 'resources';
    case Abilities = 'abilities';
    case Inventory = 'inventory';

    /**
     * Все коды в стабильном порядке.
     *
     * @return list<string> Коды.
     */
    public static function codes(): array
    {
        $codes = [];
        foreach (self::cases() as $section) {
            $codes[] = $section->value;
        }

        sort($codes, SORT_STRING);

        return $codes;
    }
}
