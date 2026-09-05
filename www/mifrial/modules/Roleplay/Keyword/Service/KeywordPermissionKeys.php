<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Service;

/**
 * Глобальные ключи прав справочника признаков.
 */
final class KeywordPermissionKeys
{
    public const CREATE = 'keyword.create';

    public const EDIT = 'keyword.edit';

    public const DELETE = 'keyword.delete';

    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }
}
