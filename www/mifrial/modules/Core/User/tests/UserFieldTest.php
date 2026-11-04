<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\User\Field\UserLinkSetField;
use Mifrial\Core\User\Field\UserReferenceField;
use Mifrial\Core\User\Table\UserTable;
use PHPUnit\Framework\TestCase;

final class UserFieldTest extends TestCase
{
    /**
     * Ссылка на учётку объявляется типом модуля и ведёт на `user`.
     *
     * @return void
     */
    public function testReferenceTargetsUser(): void
    {
        $field = new UserReferenceField('owner_id', FieldSettings::fromOptions(['required' => true]));
        self::assertSame('reference', $field->type());
        self::assertSame('user', $field->targetTableName());
        self::assertSame(UserTable::class, $field->targetDefinitionClass());
        self::assertSame('restrict', $field->onDelete());

        $cascade = new UserReferenceField(
            'user_id',
            FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
            'cascade',
        );
        self::assertSame('cascade', $cascade->onDelete());
    }

    /**
     * Набор ссылок на учётки объявляется типом модуля и ведёт на `user`.
     *
     * @return void
     */
    public function testLinkSetTargetsUser(): void
    {
        $field = new UserLinkSetField('whitelist', FieldSettings::fromOptions(['default' => []]));
        self::assertSame('linkset', $field->type());
        self::assertSame('user', $field->targetTableName());
        self::assertSame(UserTable::class, $field->targetDefinitionClass());
        self::assertSame('restrict', $field->onDelete());
    }

    /**
     * Обёртка не отменяет запреты базового поля.
     *
     * @return void
     */
    public function testWrapperKeepsBaseRules(): void
    {
        try {
            new UserReferenceField('owner_id', FieldSettings::fromOptions(['required' => true]), 'setNull');
            self::fail('required user reference must not set null');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            new UserLinkSetField('whitelist', FieldSettings::fromOptions(['unique' => true]));
            self::fail('user linkset must not be unique');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }
}
