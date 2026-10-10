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
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Schema\CharacterSchema;
use Mifrial\Roleplay\Character\Table\CharacterTable;
use Mifrial\Roleplay\Character\Table\CharacterViewerTable;
use Mifrial\Roleplay\Game\Table\GameCharacterTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

final class CharacterFormulaContextMysqlTest extends TestCase
{
    private ?ICharacters $characters = null;

    private ?ICharacterFormulaContexts $formulaContexts = null;

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
     * id читает записанный sheet тем же разбором, что документ.
     *
     * @return void
     */
    public function testStoredSheetMatchesDocument(): void
    {
        $sheet = [
            'abilityLevels' => ['strike' => 2],
            'characteristicPurchases' => [
                ['characteristicCode' => 'strength', 'cost' => 1, 'value' => ['base' => 4, 'size' => 1]],
            ],
            'money' => 3,
        ];
        $ownerId = $this->userAccounts()->addFromInput(['login' => 'owner', 'name' => 'owner']);
        $spaceId = $this->smartTableGateway()->open(RuleSpaceTable::class)->records()->add(['title' => 'World']);
        $characterId = $this->characters()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $ownerId,
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'name' => 'Hero',
            'choices' => [],
            'sheet' => $sheet,
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));

        $stored = $this->formulaContexts()->buildStored($characterId);
        $document = $this->formulaContexts()->build($sheet);
        self::assertSame($document->findAbilityLevel('strike'), $stored->findAbilityLevel('strike'));
        $storedStrength = $stored->findCharacteristic('strength');
        $documentStrength = $document->findCharacteristic('strength');
        self::assertInstanceOf(DimensionalNumber::class, $storedStrength);
        self::assertInstanceOf(DimensionalNumber::class, $documentStrength);
        self::assertSame($documentStrength->getBase(), $storedStrength->getBase());
        self::assertSame($documentStrength->getSize(), $storedStrength->getSize());
        self::assertNull($stored->findParameter('any'));
        self::assertNull($stored->findActionCharacteristic('strike', 'strength'));
    }

    /**
     * Нет строки — CHARACTER_NOT_FOUND.
     *
     * @return void
     */
    public function testMissingCharacterIsNotFound(): void
    {
        try {
            $this->formulaContexts()->buildStored(1);
            self::fail('missing character must fail');
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
        $formulaContexts = $characterContainer->get(ICharacterFormulaContexts::class);
        self::assertInstanceOf(ICharacterFormulaContexts::class, $formulaContexts);
        $this->formulaContexts = $formulaContexts;
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
        $gameCharacterSchema = $gateway->open(GameCharacterTable::class)->schema();
        if ($gameCharacterSchema->exists()) {
            $gameCharacterSchema->deleteTable();
        }

        foreach ([CharacterViewerTable::class, CharacterTable::class] as $tableClass) {
            $schema = $gateway->open($tableClass)->schema();
            if ($schema->exists()) {
                $schema->deleteTable();
            }
        }

        UserMysqlTables::drop($gateway);
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
     * Порт после setUp.
     *
     * @return ICharacterFormulaContexts Порт.
     */
    private function formulaContexts(): ICharacterFormulaContexts
    {
        self::assertInstanceOf(ICharacterFormulaContexts::class, $this->formulaContexts);

        return $this->formulaContexts;
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
