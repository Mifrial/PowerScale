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
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Core\User\Tests\UserMysqlTables;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\Action\ApplyCharacterActualPatchInput;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Schema\CharacterSchema;
use Mifrial\Roleplay\Character\Service\CharacterActualMutations;
use Mifrial\Roleplay\Character\Service\CharacterActualPatch;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Save\CharacterSheetDocument;
use Mifrial\Roleplay\Character\Table\CharacterTable;
use Mifrial\Roleplay\Character\Table\CharacterViewerTable;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use PHPUnit\Framework\TestCase;

final class CharacterActualMutationMysqlTest extends TestCase
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
     * Верное количество пишет строку и поднимает версию. Чужой ключ sheet остаётся.
     *
     * @return void
     */
    public function testQuantityBumpsVersionAndKeepsUnknownSheetKey(): void
    {
        $characterId = $this->addHero();
        $record = $this->port()->apply($characterId, 1, [$this->quantity('sword', 3)]);

        self::assertSame(2, $record->getActualVersion());
        self::assertSame(3, $record->getChoices()['inventory'][0]['quantity']);
        self::assertSame('keep', $record->getChoices()['inventory'][0]['note']);
        self::assertSame(4, $record->getChoices()['money']);
        self::assertSame('stay', $record->getSheet()['marker']);
        self::assertSame($this->sorted($this->expectedSheet()), $this->sorted($this->withoutMarker($record->getSheet())));
    }

    /**
     * setMoney пишет наличные. putInventoryQuantity создаёт строку.
     *
     * @return void
     */
    public function testMoneyAndPutQuantity(): void
    {
        $characterId = $this->addHero();
        $record = $this->port()->apply($characterId, 1, [
            ['kind' => 'setMoney', 'amount' => 9],
            ['kind' => 'putInventoryQuantity', 'ruleCode' => 'gem', 'quantity' => 2],
        ]);

        self::assertSame(9, $record->getChoices()['money']);
        self::assertSame(9, $record->getSheet()['money']);
        $gem = $record->getChoices()['inventory'][2];
        self::assertSame('gem', $gem['ruleCode']);
        self::assertSame(2, $gem['quantity']);
        self::assertFalse($gem['equipped']);
        self::assertSame('keep', $record->getChoices()['inventory'][0]['note']);
    }

    /**
     * putState пишет список после разбора, не копию до патча.
     *
     * @return void
     */
    public function testPutStateWritesParsedList(): void
    {
        $characterId = $this->addHero();
        $stored = $this->characters()->get($characterId);
        $sheet = $stored->getSheet();
        $sheet['states'] = [['stateRuleCode' => 'earlier']];
        $this->characters()->replacePayload($characterId, $stored->getChoices(), $sheet, 1);
        $port = $this->portWithSlice(new CharacterRuleSlice(1, 1, 'world', [
            new CharacterResolvedRule(
                new RuleVersionRecord(1, 1, 'mark', true, 'state', 'Name', '', ['value_type' => 'flag'], [], [], 'needs_work', '', DateTime::now()),
                [],
            ),
        ], []));

        $written = $port->apply($characterId, 2, [
            ['kind' => 'putState', 'stateRuleCode' => 'mark'],
        ]);

        self::assertSame(3, $written->getActualVersion());
        self::assertSame([
            ['stateRuleCode' => 'earlier'],
            ['stateRuleCode' => 'mark'],
        ], $written->getSheet()['states']);
        self::assertSame('stay', $written->getSheet()['marker']);
        $nine = $written->getSheet();
        unset($nine['marker'], $nine['states']);
        self::assertSame($this->sorted($this->expectedSheet()), $this->sorted($nine));
        self::assertArrayNotHasKey('damage', $written->getSheet());

        try {
            $port->apply($characterId, 1, [
                ['kind' => 'putState', 'stateRuleCode' => 'mark'],
            ]);
            self::fail('stale version must conflict');
        } catch (CharacterConflictException) {
            self::assertSame(3, $this->characters()->get($characterId)->getActualVersion());
            self::assertCount(2, $this->characters()->get($characterId)->getSheet()['states']);
        }

        try {
            $port->apply(999999, 1, [
                ['kind' => 'putState', 'stateRuleCode' => 'mark'],
            ]);
            self::fail('missing character must be not found');
        } catch (CharacterNotFoundException) {
            self::assertSame(3, $this->characters()->get($characterId)->getActualVersion());
        }
    }

    /**
     * putDamageSplit пишет замену и сумму и поднимает версию.
     *
     * @return void
     */
    public function testDamageSplitWritesReplacementAndSum(): void
    {
        $characterId = $this->addHero();
        $stored = $this->characters()->get($characterId);
        $sheet = $stored->getSheet();
        $sheet['states'] = [
            ['stateRuleCode' => 'other'],
            ['stateRuleCode' => 'hurt', 'value' => ['base' => 1, 'size' => 1]],
            ['stateRuleCode' => 'tired', 'value' => 2],
        ];
        $this->characters()->replacePayload($characterId, $stored->getChoices(), $sheet, 1);
        $port = $this->portWithSlice(new CharacterRuleSlice(1, 1, 'world', [
            new CharacterResolvedRule(
                new RuleVersionRecord(1, 1, 'hurt', true, 'state', 'Name', '', [
                    'value_type' => 'dimensional',
                    'damage_remainder' => true,
                ], [], [], 'needs_work', '', DateTime::now()),
                [],
            ),
            new CharacterResolvedRule(
                new RuleVersionRecord(1, 1, 'tired', true, 'state', 'Name', '', [
                    'value_type' => 'number',
                    'damage_exhaustion' => true,
                ], [], [], 'needs_work', '', DateTime::now()),
                [],
            ),
        ], []));

        $written = $port->apply($characterId, 2, [[
            'kind' => 'putDamageSplit',
            'remainder' => ['base' => 3, 'size' => 4],
            'quotient' => 5,
        ]]);

        self::assertSame(3, $written->getActualVersion());
        $states = $written->getSheet()['states'];
        self::assertSame('other', $states[0]['stateRuleCode']);
        self::assertSame('hurt', $states[1]['stateRuleCode']);
        self::assertSame(['base' => 3, 'size' => 4], $states[1]['value']);
        self::assertSame('tired', $states[2]['stateRuleCode']);
        self::assertSame(7, $states[2]['value']);

        try {
            $port->apply($characterId, 1, [[
                'kind' => 'putDamageSplit',
                'remainder' => ['base' => 0, 'size' => 0],
                'quotient' => 1,
            ]]);
            self::fail('stale version must conflict');
        } catch (CharacterConflictException) {
            self::assertSame(3, $this->characters()->get($characterId)->getActualVersion());
            self::assertSame(7, $this->characters()->get($characterId)->getSheet()['states'][2]['value']);
        }

        try {
            $port->apply(999999, 1, [[
                'kind' => 'putDamageSplit',
                'remainder' => ['base' => 0, 'size' => 0],
                'quotient' => 1,
            ]]);
            self::fail('missing character must be not found');
        } catch (CharacterNotFoundException) {
            self::assertSame(3, $this->characters()->get($characterId)->getActualVersion());
        }
    }

    /**
     * Старая версия не пишет лист.
     *
     * @return void
     */
    public function testStaleVersionDoesNotWrite(): void
    {
        $characterId = $this->addHero();
        try {
            $this->port()->apply($characterId, 9, [$this->quantity('sword', 3)]);
            self::fail('stale version must conflict');
        } catch (CharacterConflictException $exception) {
            self::assertSame(1, $exception->getCurrentVersion());
            self::assertSame(['currentVersion' => 1], $exception->getErrorDetails());
        }

        $record = $this->characters()->get($characterId);
        self::assertSame(1, $record->getActualVersion());
        self::assertSame(1, $record->getChoices()['inventory'][0]['quantity']);
    }

    /**
     * Две операции пишутся вместе. Вторая с чужим кодом не оставляет первую.
     *
     * @return void
     */
    public function testTwoOperationsCommitTogetherAndRejectLeavesBoth(): void
    {
        $characterId = $this->addHero();
        $record = $this->port()->apply($characterId, 1, [
            $this->quantity('sword', 2),
            $this->quantity('rope', 4),
        ]);
        self::assertSame(2, $record->getChoices()['inventory'][0]['quantity']);
        self::assertSame(4, $record->getChoices()['inventory'][1]['quantity']);

        try {
            $this->port()->apply($characterId, 2, [
                $this->quantity('sword', 9),
                $this->quantity('missing', 1),
            ]);
            self::fail('missing code must reject');
        } catch (CharacterInvalidException) {
            $fresh = $this->characters()->get($characterId);
            self::assertSame(2, $fresh->getActualVersion());
            self::assertSame(2, $fresh->getChoices()['inventory'][0]['quantity']);
        }
    }

    /**
     * Повтор кода в списке оставляет последнее количество.
     *
     * @return void
     */
    public function testRepeatedRuleCodeKeepsLastQuantity(): void
    {
        $record = $this->port()->apply($this->addHero(), 1, [
            $this->quantity('sword', 2),
            $this->quantity('sword', 6),
        ]);

        self::assertSame(6, $record->getChoices()['inventory'][0]['quantity']);
        self::assertSame(2, $record->getActualVersion());
    }

    /**
     * Две строки с одним кодом, чужой kind и отрицательное количество не пишут.
     *
     * @return void
     */
    public function testAmbiguousCodeAndForeignKindDoNotWrite(): void
    {
        $characterId = $this->addHero();
        $choices = $this->characters()->get($characterId)->getChoices();
        $choices['inventory'][] = ['ruleCode' => 'sword', 'quantity' => 1];
        $this->characters()->replacePayload($characterId, $choices, $this->characters()->get($characterId)->getSheet(), 1);

        foreach ([
            [$this->quantity('sword', 3)],
            [['kind' => 'replaceSection', 'section' => 'states', 'value' => []]],
            [$this->quantity('rope', -1)],
        ] as $operations) {
            try {
                $this->port()->apply($characterId, 2, $operations);
                self::fail('invalid patch must reject');
            } catch (CharacterInvalidException) {
                self::assertSame(2, $this->characters()->get($characterId)->getActualVersion());
            }
        }
    }

    /**
     * Чужой владелец не открывает порт.
     *
     * @return void
     */
    public function testForeignOwnerIsNotFound(): void
    {
        $characterId = $this->addHero();
        $access = $this->createMock(IUserAccess::class);
        $access->method('requireActor')->willReturn(new RequestActor(8, [], false));
        $patch = new CharacterActualPatch($access, $this->characters(), $this->port());

        $this->expectException(CharacterNotFoundException::class);
        $patch->apply(new ApplyCharacterActualPatchInput($characterId, 1, [$this->quantity('sword', 3)]));
    }

    /**
     * Операция количества.
     *
     * @param string $ruleCode Код.
     * @param int $quantity Число.
     *
     * @return array{kind: string, ruleCode: string, quantity: int} Операция.
     */
    private function quantity(string $ruleCode, int $quantity): array
    {
        return ['kind' => 'setInventoryQuantity', 'ruleCode' => $ruleCode, 'quantity' => $quantity];
    }

    /**
     * Порт с пустым validate. Сборка sheet настоящая.
     *
     * @return ICharacterActualMutations Порт.
     */
    private function port(): ICharacterActualMutations
    {
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturn(new CharacterRuleSlice(1, 1, 'world', [], []));
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->method('validate')->willReturn(new CharacterValidation([], [], [], 0, [], [], [], true));

        return $this->portWithSlice(new CharacterRuleSlice(1, 1, 'world', [], []));
    }

    /**
     * Порт с заданным срезом. validate пустой, сборка sheet настоящая.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return ICharacterActualMutations Порт.
     */
    private function portWithSlice(CharacterRuleSlice $slice): ICharacterActualMutations
    {
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturn($slice);
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->method('validate')->willReturn(new CharacterValidation([], [], [], 0, [], [], [], true));

        return new CharacterActualMutations(
            $this->characters(),
            $slices,
            $sheets,
            new CharacterChoiceAssembler(),
            new CharacterSheetDocument(),
        );
    }

    /**
     * Девять ключей пустого снимка с наличными 4.
     *
     * @return array<string, mixed> Sheet.
     */
    private function expectedSheet(): array
    {
        return (new CharacterSheetDocument())->build(
            new CharacterValidation([], [], [], 0, [], [], [], true),
            4,
        );
    }

    /**
     * Sheet без проверочного ключа.
     *
     * @param array<string, mixed> $sheet Снимок.
     *
     * @return array<string, mixed> Девять ключей.
     */
    private function withoutMarker(array $sheet): array
    {
        unset($sheet['marker']);

        return $sheet;
    }

    /**
     * Сравнение JSON без порядка ключей.
     *
     * @param array<string, mixed> $sheet Снимок.
     *
     * @return array<string, mixed> Отсортированный снимок.
     */
    private function sorted(array $sheet): array
    {
        ksort($sheet);

        return $sheet;
    }

    /**
     * Персонаж с двумя предметами и чужим ключом sheet.
     *
     * @return int Id.
     */
    private function addHero(): int
    {
        return $this->characters()->add($this->newCharacter($this->addUser('owner'), $this->addSpace(), [
            'choices' => [
                'name' => 'Hero',
                'raceCode' => '',
                'abilities' => [],
                'inventory' => [
                    ['ruleCode' => 'sword', 'quantity' => 1, 'equipped' => false, 'note' => 'keep'],
                    ['ruleCode' => 'rope', 'quantity' => 1, 'equipped' => false],
                ],
                'characteristicPurchases' => [],
                'customRules' => [],
                'active' => true,
                'money' => 4,
            ],
            'sheet' => ['marker' => 'stay', 'money' => 4],
        ]));
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
            'login' => $login . bin2hex(random_bytes(3)),
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
