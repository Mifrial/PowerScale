<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Enum;

/**
 * Смысл полигона на карте.
 */
enum MapAreaKind: string
{
    case Building = 'building';
    case Park = 'park';
    case Water = 'water';
    case Bazaar = 'bazaar';
    case Wall = 'wall';
    case Other = 'other';
}
