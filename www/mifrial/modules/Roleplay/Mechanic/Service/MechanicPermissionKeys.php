<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service;

/**
 * Глобальные ключи прав справочника механик.
 */
final class MechanicPermissionKeys
{
    public const CREATE = 'mechanic.create';

    public const EDIT = 'mechanic.edit';

    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }
}
