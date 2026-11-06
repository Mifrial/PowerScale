<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

/**
 * Глобальные ключи прав карт.
 */
final class MapPermissionKeys
{
    public const VIEW = 'map.view';

    public const EDIT = 'map.edit';

    public const VIEW_ALL = 'map.view_all';

    public const EDIT_ALL = 'map.edit_all';

    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }
}
