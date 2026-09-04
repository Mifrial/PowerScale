<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Dto\GroupRecord;
use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Exception\UserInvalidException;
use PHPUnit\Framework\TestCase;

final class RecordFromNormalizedTest extends TestCase
{
    /**
     * Строка login/name и int id; приведение типов отвергается.
     *
     * @return void
     */
    public function testUserRecordRejectsWrongTypes(): void
    {
        $valid = $this->userFields();
        $userRecord = UserRecord::fromNormalized($valid);
        self::assertSame(1, $userRecord->getId());
        self::assertSame('alice', $userRecord->getLogin());
        self::assertFalse($userRecord->isActive());
        try {
            UserRecord::fromNormalized([...$valid, 'login' => 15]);
            self::fail('login int');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }

        try {
            UserRecord::fromNormalized([...$valid, 'id' => '1']);
            self::fail('id string');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }

        try {
            UserRecord::fromNormalized([...$valid, 'active' => 1]);
            self::fail('active int');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Имя группы — строка, id — int, флаги — bool.
     *
     * @return void
     */
    public function testGroupRecordRejectsWrongTypes(): void
    {
        $valid = $this->groupFields();
        $groupRecord = GroupRecord::fromNormalized($valid);
        self::assertSame('One', $groupRecord->getName());
        self::assertTrue($groupRecord->isBypass());
        try {
            GroupRecord::fromNormalized([...$valid, 'name' => 2]);
            self::fail('name int');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Полный набор учётки.
     *
     * @return array<string, mixed> Поля.
     */
    private function userFields(): array
    {
        return [
            'id' => 1,
            'login' => 'alice',
            'email' => null,
            'name' => 'Alice',
            'surname' => null,
            'nickname' => null,
            'active' => false,
            'registered_at' => DateTime::fromUnix(1),
            'deactivated_until' => null,
            'deactivate_reason' => null,
        ];
    }

    /**
     * Полный набор группы.
     *
     * @return array<string, mixed> Поля.
     */
    private function groupFields(): array
    {
        return [
            'id' => 2,
            'name' => 'One',
            'active' => true,
            'bypass' => true,
            'assign_on_register' => false,
            'created_at' => DateTime::fromUnix(1),
            'permissions' => ['user.view'],
        ];
    }
}
