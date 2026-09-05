<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

/**
 * Глобальные ключи прав мира (категория Vue `space`, не action `ruleSpace.*`).
 */
final class RuleSpacePermissionKeys
{
    public const CREATE = 'space.create';

    public const VIEW_ALL = 'space.view_all';

    public const EDIT_ALL = 'space.edit_all';

    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }
}
