<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Глобальные ключи игры. Каталог прав этим шагом не расширяется.
 */
final class GamePermissionKeys
{
    public const CREATE = 'game.create';

    public const VIEW_ALL = 'game.view_all';

    public const EDIT_ALL = 'game.edit_all';

    public const EDIT = 'game.edit';

    public const MODERATE = 'game.moderate';

    public const MANAGE = 'game.manage';

    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }

    /**
     * Ключи владельца колонки или роли строки. В таблицу не пишутся.
     *
     * @param bool $isOwner Актор — owner_id.
     * @param string|null $role Роль строки или null.
     *
     * @return list<string> Ключи.
     */
    public static function keysFor(bool $isOwner, ?string $role): array
    {
        if ($isOwner) {
            return [self::EDIT, self::MODERATE, self::MANAGE];
        }

        if ($role === 'gm') {
            return [self::EDIT, self::MODERATE];
        }

        return [];
    }
}
