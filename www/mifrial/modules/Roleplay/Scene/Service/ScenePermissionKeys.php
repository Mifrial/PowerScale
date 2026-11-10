<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

/**
 * Глобальные ключи прав локаций сцен.
 */
final class ScenePermissionKeys
{
    public const VIEW = 'scene.view';

    public const EDIT = 'scene.edit';

    public const VIEW_ALL = 'scene.view_all';

    public const EDIT_ALL = 'scene.edit_all';

    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }
}
