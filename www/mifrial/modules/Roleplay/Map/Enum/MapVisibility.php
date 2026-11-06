<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Enum;

/**
 * Видимость карты для чужих акторов.
 */
enum MapVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
