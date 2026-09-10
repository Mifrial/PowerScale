<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Core\User\Tests\UserMysqlTables;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Schema\CharacterSchema;
use Mifrial\Roleplay\Character\Table\CharacterTable;
use Mifrial\Roleplay\Character\Table\CharacterViewerTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use PHPUnit\Framework\TestCase;

final class CharacterMysqlTest extends TestCase
{
    private ?ICharacters $characters = null;

    private ?IUserAccounts $userAccounts = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectCharacter();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Character tests');
        }

        $this->dropCharacterTables();
        $this->installSchemas();
    }

    /**
     * Снос таблиц.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropCharacterTables();
    }

    /**
     * add+get: version 1, не public.
     *
     * @return void
     */
    public function testAddAndGet(): void
    {
        $ownerId = $this->addUser('owner');
        $spaceId = $this->addSpace();
        $characters = $this->characters();
        $characterId = $characters->add($this->newCharacter($ownerId, $spaceId, [
            'name' => '  Hero  ',
            'choices' => ['race' => 'human'],
            'sheet' => ['os' => 1],
            'ownerNotes' => 'note',
        ]));
        $characterRecord = $characters->get($characterId);
        self::assertSame($characterId, $characterRecord->getId());
        self::assertSame($ownerId, $characterRecord->getOwnerId());
        self::assertSame($spaceId, $characterRecord->getSpaceId());
        self::assertSame(1, $characterRecord->getRulesRevision());
        self::assertSame('Hero', $characterRecord->getName());
        self::assertTrue($characterRecord->isActive());
        self::assertSame(1, $characterRecord->getActualVersion());
        self::assertSame(['race' => 'human'], $characterRecord->getChoices());
        self::assertSame(['os' => 1], $characterRecord->getSheet());
        self::assertSame([], $characterRecord->getVisibilityFields());
        self::assertFalse($characterRecord->isPublic());
        self::assertSame('note', $characterRecord->getOwnerNotes());
    }

    /**
     * Секции сортируются, is_public true.
     *
     * @return void
     */
    public function testVisibilityFieldsAreSortedAndPublic(): void
    {
        $characterRecord = $this->characters()->get(
            $this->characters()->add($this->newCharacter($this->addUser('vis'), $this->addSpace(), [
                'visibilityFields' => ['race', 'inventory'],
            ])),
        );
        self::assertSame(['inventory', 'race'], $characterRecord->getVisibilityFields());
        self::assertTrue($characterRecord->isPublic());
    }

    /**
     * Дубль секции.
     *
     * @return void
     */
    public function testDuplicateVisibilityFieldIsInvalid(): void
    {
        try {
            $this->characters()->add($this->newCharacter($this->addUser('dup'), $this->addSpace(), [
                'visibilityFields' => ['race', 'race'],
            ]));
            self::fail('duplicate section must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет user / нет часов.
     *
     * @return void
     */
    public function testMissingUserOrSpaceIsNotFound(): void
    {
        $spaceId = $this->addSpace();
        try {
            $this->characters()->add($this->newCharacter(999_999, $spaceId, []));
            self::fail('missing user must fail');
        } catch (CharacterNotFoundException $exception) {
            self::assertSame('CHARACTER_NOT_FOUND', $exception->getErrorCode());
        }

        $ownerId = $this->addUser('lonely');
        try {
            $this->characters()->add($this->newCharacter($ownerId, 999_999, []));
            self::fail('missing space must fail');
        } catch (CharacterNotFoundException $exception) {
            self::assertSame('CHARACTER_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Пустое имя, нулевые ids, expectedVersion 0.
     *
     * @return void
     */
    public function testInvalidNameRevisionSpaceAndExpectedVersion(): void
    {
        $ownerId = $this->addUser('bad');
        $spaceId = $this->addSpace();
        $characters = $this->characters();
        try {
            $characters->add($this->newCharacter($ownerId, $spaceId, ['name' => '  ']));
            self::fail('empty name must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        try {
            $characters->add($this->newCharacter($ownerId, $spaceId, ['rulesRevision' => 0]));
            self::fail('revision 0 must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        try {
            $characters->add($this->newCharacter($ownerId, 0, []));
            self::fail('space 0 must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        try {
            $characters->add($this->newCharacter(999_999, $spaceId, ['name' => '  ']));
            self::fail('empty name must fail before missing user');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        $characterId = $characters->add($this->newCharacter($ownerId, $spaceId, []));
        try {
            $characters->replacePayload($characterId, [], [], 0);
            self::fail('expectedVersion 0 must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Скаляр choices и object visibility.
     *
     * @return void
     */
    public function testInvalidJsonPayloads(): void
    {
        $ownerId = $this->addUser('json');
        $spaceId = $this->addSpace();
        try {
            NewCharacter::fromNormalized([
                'ownerUserId' => $ownerId,
                'spaceId' => $spaceId,
                'rulesRevision' => 1,
                'name' => 'A',
                'choices' => 'nope',
                'sheet' => [],
            ]);
            self::fail('scalar choices must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        try {
            $this->characters()->add($this->newCharacter($ownerId, $spaceId, [
                'visibilityFields' => ['a' => 'race'],
            ]));
            self::fail('visibility object must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * replacePayload bump version, visibility не трогает.
     *
     * @return void
     */
    public function testReplacePayloadBumpsVersionAndKeepsVisibility(): void
    {
        $characters = $this->characters();
        $characterId = $characters->add($this->newCharacter($this->addUser('rep'), $this->addSpace(), [
            'visibilityFields' => ['inventory'],
            'choices' => ['a' => 1],
        ]));
        $updated = $characters->replacePayload($characterId, ['b' => 2], ['sheet' => true], 1);
        self::assertSame(2, $updated->getActualVersion());
        self::assertSame(['b' => 2], $updated->getChoices());
        self::assertSame(['sheet' => true], $updated->getSheet());
        self::assertSame(['inventory'], $updated->getVisibilityFields());
        self::assertTrue($updated->isPublic());
        self::assertTrue($updated->isActive());
    }

    /**
     * Stale replace и setActive.
     *
     * @return void
     */
    public function testStaleVersionConflicts(): void
    {
        $characters = $this->characters();
        $characterId = $characters->add($this->newCharacter($this->addUser('stale'), $this->addSpace(), [
            'choices' => ['keep' => true],
        ]));
        $characters->replacePayload($characterId, ['new' => true], [], 1);
        try {
            $characters->replacePayload($characterId, ['lost' => true], [], 1);
            self::fail('stale replace must fail');
        } catch (CharacterConflictException $exception) {
            self::assertSame('CHARACTER_CONFLICT', $exception->getErrorCode());
            self::assertSame(2, $exception->getCurrentVersion());
        }

        self::assertSame(['new' => true], $characters->get($characterId)->getChoices());
        try {
            $characters->setActive($characterId, false, 1);
            self::fail('stale setActive must fail');
        } catch (CharacterConflictException $exception) {
            self::assertSame(2, $exception->getCurrentVersion());
        }

        self::assertTrue($characters->get($characterId)->isActive());
    }

    /**
     * setActive выключает и bump.
     *
     * @return void
     */
    public function testSetActive(): void
    {
        $characters = $this->characters();
        $characterId = $characters->add($this->newCharacter($this->addUser('act'), $this->addSpace(), [
            'choices' => ['x' => 1],
        ]));
        $updated = $characters->setActive($characterId, false, 1);
        self::assertFalse($updated->isActive());
        self::assertSame(2, $updated->getActualVersion());
        self::assertSame(['x' => 1], $updated->getChoices());
    }

    /**
     * Два персонажа с одним именем.
     *
     * @return void
     */
    public function testDuplicateNameIsAllowed(): void
    {
        $ownerId = $this->addUser('twins');
        $spaceId = $this->addSpace();
        $characters = $this->characters();
        $firstId = $characters->add($this->newCharacter($ownerId, $spaceId, ['name' => 'Same']));
        $secondId = $characters->add($this->newCharacter($ownerId, $spaceId, ['name' => 'Same']));
        self::assertNotSame($firstId, $secondId);
    }

    /**
     * Нет строки.
     *
     * @return void
     */
    public function testMissingRowIsNotFound(): void
    {
        $characters = $this->characters();
        try {
            $characters->get(1);
            self::fail('missing get must fail');
        } catch (CharacterNotFoundException $exception) {
            self::assertSame('CHARACTER_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $characters->replacePayload(1, [], [], 1);
            self::fail('missing replace must fail');
        } catch (CharacterNotFoundException $exception) {
            self::assertSame('CHARACTER_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $characters->setActive(1, false, 1);
            self::fail('missing setActive must fail');
        } catch (CharacterNotFoundException $exception) {
            self::assertSame('CHARACTER_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * boot и ленивый get.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectCharacter(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $characterContainer = $application->getLocator()->get(ICharacterContainer::class);
        $characters = $characterContainer->get(ICharacters::class);
        self::assertInstanceOf(ICharacters::class, $characters);
        $this->characters = $characters;
        $userContainer = $application->getLocator()->get(IUserContainer::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        $this->userAccounts = $userAccounts;
        $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->smartTableGateway = $smartTableGateway;
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            self::markTestSkipped('test connection is not Illuminate');
        }

        $databaseConnection->ping();
    }

    /**
     * User, часы, Character.
     *
     * @return void
     */
    private function installSchemas(): void
    {
        $gateway = $this->smartTableGateway();
        (new UserSchema(
            $gateway->open(UserTable::class)->schema(),
            $gateway->open(UserGroupTable::class)->schema(),
            $gateway->open(UserGroupMemberTable::class)->schema(),
        ))->install();
        $spaceSchema = $gateway->open(RuleSpaceTable::class)->schema();
        if ($spaceSchema->exists()) {
            $spaceSchema->updateTable();
        } else {
            $spaceSchema->createTable();
        }

        (new CharacterSchema(
            $gateway->open(CharacterTable::class)->schema(),
            $gateway->open(CharacterViewerTable::class)->schema(),
        ))->install();
        self::assertTrue($gateway->open(CharacterViewerTable::class)->schema()->exists());
        self::assertTrue($gateway->open(CharacterTable::class)->schema()->exists());
    }

    /**
     * Drop FK-порядка.
     *
     * @return void
     */
    private function dropCharacterTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $gateway = $this->smartTableGateway;
        foreach ([CharacterViewerTable::class, CharacterTable::class] as $tableClass) {
            $schema = $gateway->open($tableClass)->schema();
            if ($schema->exists()) {
                $schema->deleteTable();
            }
        }

        UserMysqlTables::drop($gateway);
    }

    /**
     * Учётка.
     *
     * @param string $login Логин.
     *
     * @return int Id.
     */
    private function addUser(string $login): int
    {
        return $this->userAccounts()->addFromInput([
            'login' => $login,
            'name' => $login,
        ]);
    }

    /**
     * Строка часов.
     *
     * @return int space_id.
     */
    private function addSpace(): int
    {
        return $this->smartTableGateway()->open(RuleSpaceTable::class)->records()->add([
            'title' => 'World',
        ]);
    }

    /**
     * New с дефолтами.
     *
     * @param int $ownerUserId Владелец.
     * @param int $spaceId Часы.
     * @param array<string, mixed> $overrides Поля.
     *
     * @return NewCharacter DTO.
     */
    private function newCharacter(int $ownerUserId, int $spaceId, array $overrides): NewCharacter
    {
        return NewCharacter::fromNormalized(array_merge([
            'ownerUserId' => $ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'name' => 'Hero',
            'choices' => [],
            'sheet' => [],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ], $overrides));
    }

    /**
     * Фасад после setUp.
     *
     * @return ICharacters Фасад.
     */
    private function characters(): ICharacters
    {
        self::assertInstanceOf(ICharacters::class, $this->characters);

        return $this->characters;
    }

    /**
     * Учётки после setUp.
     *
     * @return IUserAccounts Фасад.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Шлюз после setUp.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }
}
