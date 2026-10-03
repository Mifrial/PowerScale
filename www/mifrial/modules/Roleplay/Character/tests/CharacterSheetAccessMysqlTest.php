<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
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
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Repository\CharacterVisibilityRepository;
use Mifrial\Roleplay\Character\Schema\CharacterSchema;
use Mifrial\Roleplay\Character\Service\CharacterSheetAccess;
use Mifrial\Roleplay\Character\Service\Read\CharacterSectionCodes;
use Mifrial\Roleplay\Character\Service\Read\CharacterViewerParser;
use Mifrial\Roleplay\Character\Table\CharacterTable;
use Mifrial\Roleplay\Character\Table\CharacterViewerTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use PHPUnit\Framework\TestCase;

final class CharacterSheetAccessMysqlTest extends TestCase
{
    private ?ICharacters $characters = null;

    private ?IUserAccounts $userAccounts = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    private ?CharacterSheetAccess $sheetAccess = null;

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
     * Владелец без character.view видит свой лист. Чужой без секций — NotFound.
     *
     * @return void
     */
    public function testOwnerSeesSheetAndStrangerDoesNot(): void
    {
        $ownerId = $this->addUser('owner');
        $strangerId = $this->addUser('stranger');
        $characterId = $this->characters()->add($this->newCharacter($ownerId, $this->addSpace()));
        $owner = new RequestActor($ownerId, [], false);
        $stranger = new RequestActor($strangerId, ['character.view'], false);

        $opening = $this->sheetAccess()->open($owner, $characterId);
        self::assertTrue($opening->isForOwner());
        self::assertSame([$characterId], $this->ids($this->sheetAccess()->visibleList($owner)));

        try {
            $this->sheetAccess()->open($stranger, $characterId);
            self::fail('hidden sheet must be not found');
        } catch (CharacterNotFoundException $exception) {
            self::assertSame('CHARACTER_NOT_FOUND', $exception->getErrorCode());
        }

        self::assertSame([], $this->sheetAccess()->visibleList($stranger));
    }

    /**
     * Неизвестный код делает is_public, но не даёт ни списка, ни GET.
     *
     * @return void
     */
    public function testUnknownPublicCodeDoesNotLeak(): void
    {
        $ownerId = $this->addUser('owner-unknown');
        $readerId = $this->addUser('reader-unknown');
        $characterId = $this->characters()->add($this->newCharacter($ownerId, $this->addSpace(), [
            'visibilityFields' => ['nope'],
        ]));
        $owner = new RequestActor($ownerId, [], false);
        $reader = new RequestActor($readerId, ['character.view'], false);

        self::assertTrue($this->sheetAccess()->open($owner, $characterId)->getRecord()->isPublic());
        self::assertSame([$characterId], $this->ids($this->sheetAccess()->visibleList($owner)));
        self::assertSame([], $this->sheetAccess()->visibleList($reader));
        try {
            $this->sheetAccess()->open($reader, $characterId);
            self::fail('unknown section must not open the sheet');
        } catch (CharacterNotFoundException $exception) {
            self::assertSame('CHARACTER_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Public и зритель попадают в list. GET объединяет секции. Версия не растёт.
     *
     * @return void
     */
    public function testVisibilityListMaskAndVersion(): void
    {
        $ownerId = $this->addUser('owner2');
        $viewerId = $this->addUser('viewer');
        $publicReaderId = $this->addUser('reader');
        $characterId = $this->characters()->add($this->newCharacter($ownerId, $this->addSpace(), [
            'choices' => [
                'name' => 'Hero',
                'raceCode' => 'elf',
                'inventory' => [['ruleCode' => 'sword']],
                'limits' => ['os' => 1],
                'customRules' => [],
            ],
            'sheet' => ['racialAbilityCodes' => ['sight'], 'equippedModifiers' => [], 'active' => true],
        ]));
        $owner = new RequestActor($ownerId, [], false);
        $saved = $this->sheetAccess()->replaceVisibility($owner, $characterId, ['race'], [
            ['userId' => $viewerId, 'fields' => ['inventory']],
        ]);

        self::assertTrue($saved->getRecord()->isPublic());
        self::assertSame(1, $saved->getRecord()->getActualVersion());
        self::assertSame([$viewerId], array_map(
            static fn ($viewer): int => $viewer->getUserId(),
            $saved->getViewers(),
        ));

        $viewer = new RequestActor($viewerId, ['character.view'], false);
        self::assertSame([$characterId], $this->ids($this->sheetAccess()->visibleList($viewer)));
        $opening = $this->sheetAccess()->open($viewer, $characterId);
        self::assertSame(['inventory', 'race'], $opening->getSections());
        self::assertSame([], $opening->getViewers());

        $reader = new RequestActor($publicReaderId, ['character.view'], false);
        self::assertSame([$characterId], $this->ids($this->sheetAccess()->visibleList($reader)));
        self::assertSame(['race'], $this->sheetAccess()->open($reader, $characterId)->getSections());

        $withoutView = new RequestActor($publicReaderId, [], false);
        self::assertSame([], $this->sheetAccess()->visibleList($withoutView));

        $cleared = $this->sheetAccess()->replaceVisibility($owner, $characterId, ['race'], []);
        self::assertSame([], $cleared->getViewers());
        self::assertSame(['race'], $this->sheetAccess()->open($viewer, $characterId)->getSections());
        $hidden = $this->sheetAccess()->replaceVisibility($owner, $characterId, [], []);
        self::assertFalse($hidden->getRecord()->isPublic());
        try {
            $this->sheetAccess()->open($viewer, $characterId);
            self::fail('sheet without sections must be not found');
        } catch (CharacterNotFoundException $exception) {
            self::assertSame('CHARACTER_NOT_FOUND', $exception->getErrorCode());
        }

        $noted = $this->sheetAccess()->replaceOwnerNotes($owner, $characterId, 'mine');
        self::assertSame('mine', $noted->getRecord()->getOwnerNotes());
        self::assertSame(1, $noted->getRecord()->getActualVersion());
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
        $sectionCodes = new CharacterSectionCodes();
        $this->sheetAccess = new CharacterSheetAccess(
            new CharacterVisibilityRepository($smartTableGateway),
            $sectionCodes,
            new CharacterViewerParser($sectionCodes),
        );
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
    private function newCharacter(int $ownerUserId, int $spaceId, array $overrides = []): NewCharacter
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
     * Id строк списка.
     *
     * @param array<int, \Mifrial\Roleplay\Character\Dto\CharacterRecord> $records Строки.
     *
     * @return list<int> Id.
     */
    private function ids(array $records): array
    {
        $characterIds = [];
        foreach ($records as $record) {
            $characterIds[] = $record->getId();
        }

        return $characterIds;
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
     * Доступ после setUp.
     *
     * @return CharacterSheetAccess Доступ.
     */
    private function sheetAccess(): CharacterSheetAccess
    {
        self::assertInstanceOf(CharacterSheetAccess::class, $this->sheetAccess);

        return $this->sheetAccess;
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
