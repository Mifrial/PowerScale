<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

/**
 * Глобальные ключи прав кодекса.
 */
final class LorePermissionKeys
{
    public const CREATE = 'lore.create';

    public const VIEW_ALL = 'lore.view_all';

    public const EDIT_ALL = 'lore.edit_all';

    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }
}
