<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Keyword\Schema\KeywordSchema;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Mechanic\Schema\MechanicSchema;
use Mifrial\Roleplay\Mechanic\Table\MechanicTable;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Exception\RuleNotFoundException;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\Rule\Schema\RuleSchema;
use Mifrial\Roleplay\Rule\Table\RuleRevisionItemTable;
use Mifrial\Roleplay\Rule\Table\RuleRevisionTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use Mifrial\Roleplay\Rule\Table\RuleTable;
use Mifrial\Roleplay\Rule\Table\RuleVersionTable;
use PHPUnit\Framework\TestCase;

final class RuleMysqlTest extends TestCase
{
    private ?IRules $rules = null;

    private ?IKeywords $keywords = null;

    private ?IMechanics $mechanics = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    /**
     * Подключается к MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectRule();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Rule tests');
        }

        $this->dropTables();
        $this->installSchemas();
    }

    /**
     * Снимает таблицы.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropTables();
    }

    /**
     * Ссылки keyword и mechanic на снимке.
     *
     * @return void
     */
    public function testCommitStoresKeywordAndMechanicIds(): void
    {
        $keywordId = $this->keywords()->add('melee', 'Ближний');
        $mechanicId = $this->mechanics()->add('six_one_rule', '6 и 1', '', '1');
        $rules = $this->rules();
        $spaceId = $rules->addSpace('World A');
        $revision = $rules->commit($spaceId, [
            RuleCommitEntry::put('human', $this->body('Human', [$keywordId], $mechanicId, ['dice' => 6])),
        ]);
        self::assertSame(1, $revision->getRevision());
        $item = $rules->getRevision($spaceId, 1)->getItems()[0];
        self::assertSame('human', $item->getCode());
        self::assertSame([$keywordId], $item->getKeywordIds());
        self::assertSame($mechanicId, $item->getMechanicId());
        self::assertSame(['dice' => 6], $item->getMechanicPayload());
    }

    /**
     * Чужой mechanic_id.
     *
     * @return void
     */
    public function testUnknownMechanicIdIsInvalid(): void
    {
        $rules = $this->rules();
        $spaceId = $rules->addSpace('World');
        try {
            $rules->commit($spaceId, [
                RuleCommitEntry::put('human', $this->body('Human', [], 999, [])),
            ]);
            self::fail('missing mechanic must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Один code — одна identity в двух space.
     *
     * @return void
     */
    public function testSameCodeSharesIdentityAcrossSpaces(): void
    {
        $rules = $this->rules();
        $spaceA = $rules->addSpace('A');
        $spaceB = $rules->addSpace('B');
        $rules->commit($spaceA, [RuleCommitEntry::put('human', $this->body('Alpha'))]);
        $rules->commit($spaceB, [RuleCommitEntry::put('human', $this->body('Beta'))]);
        $itemA = $rules->getRevision($spaceA, 1)->getItems()[0];
        $itemB = $rules->getRevision($spaceB, 1)->getItems()[0];
        self::assertSame($itemA->getEntityId(), $itemB->getEntityId());
        self::assertNotSame($itemA->getVersionId(), $itemB->getVersionId());
        self::assertSame('Alpha', $itemA->getName());
        self::assertSame('Beta', $itemB->getName());
    }

    /**
     * Повторный put того же code — новая версия, не unique.
     *
     * @return void
     */
    public function testSecondPutSameCodeIsNewVersion(): void
    {
        $rules = $this->rules();
        $spaceId = $rules->addSpace('A');
        $first = $rules->commit($spaceId, [RuleCommitEntry::put('human', $this->body('One'))]);
        $second = $rules->commit($spaceId, [RuleCommitEntry::put('human', $this->body('Two'))]);
        self::assertSame(1, $first->getRevision());
        self::assertSame(2, $second->getRevision());
        self::assertSame('One', $rules->getRevision($spaceId, 1)->getItems()[0]->getName());
        self::assertSame('Two', $rules->getRevision($spaceId, 2)->getItems()[0]->getName());
        self::assertSame(
            $rules->getRevision($spaceId, 1)->getItems()[0]->getEntityId(),
            $rules->getRevision($spaceId, 2)->getItems()[0]->getEntityId(),
        );
    }

    /**
     * Флаг: keep без смены тел — новый номер, тот же version_id.
     *
     * @return void
     */
    public function testAllowUnchangedComposition(): void
    {
        $rules = $this->rules();
        $spaceId = $rules->addSpace('A');
        $rules->commit($spaceId, [RuleCommitEntry::put('human', $this->body('One'))]);
        $item = $rules->getRevision($spaceId, 1)->getItems()[0];
        $second = $rules->commit($spaceId, [RuleCommitEntry::keep($item->getVersionId())], true);
        self::assertSame(2, $second->getRevision());
        self::assertSame($item->getVersionId(), $rules->getRevision($spaceId, 2)->getItems()[0]->getVersionId());
    }

    /**
     * Два put одного code в одном commit.
     *
     * @return void
     */
    public function testTwoPutsSameCodeInOneCommitAreInvalid(): void
    {
        $rules = $this->rules();
        $spaceId = $rules->addSpace('A');
        try {
            $rules->commit($spaceId, [
                RuleCommitEntry::put('human', $this->body('A')),
                RuleCommitEntry::put('human', $this->body('B')),
            ]);
            self::fail('duplicate put must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * keep и put одной identity.
     *
     * @return void
     */
    public function testKeepAndPutSameIdentityAreInvalid(): void
    {
        $rules = $this->rules();
        $spaceId = $rules->addSpace('A');
        $rules->commit($spaceId, [RuleCommitEntry::put('human', $this->body('One'))]);
        $versionId = $rules->getRevision($spaceId, 1)->getItems()[0]->getVersionId();
        try {
            $rules->commit($spaceId, [
                RuleCommitEntry::keep($versionId),
                RuleCommitEntry::put('human', $this->body('Two')),
            ]);
            self::fail('keep+put must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет в составе, identity может быть.
     *
     * @return void
     */
    public function testFindInRevisionMissesOtherSpace(): void
    {
        $rules = $this->rules();
        $spaceA = $rules->addSpace('A');
        $spaceB = $rules->addSpace('B');
        $rules->commit($spaceA, [RuleCommitEntry::put('human', $this->body('A'))]);
        $rules->commit($spaceB, [RuleCommitEntry::put('orc', $this->body('Orc'))]);
        try {
            $rules->findInRevision($spaceB, 1, 'human');
            self::fail('missing in composition must fail');
        } catch (RuleNotFoundException $exception) {
            self::assertSame('RULE_NOT_FOUND', $exception->getErrorCode());
        }

        $found = $rules->findInRevision($spaceA, 1, 'human');
        self::assertSame('human', $found->getCode());
    }

    /**
     * Пустые входы.
     *
     * @return void
     */
    public function testEmptyInputsAreInvalid(): void
    {
        $rules = $this->rules();
        try {
            $rules->addSpace('  ');
            self::fail('empty space must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }

        $spaceId = $rules->addSpace('A');
        try {
            $rules->commit($spaceId, []);
            self::fail('empty commit must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет ревизий — null; неизвестный space — RULE_NOT_FOUND.
     *
     * @return void
     */
    public function testFindLatestRevision(): void
    {
        $rules = $this->rules();
        $spaceId = $rules->addSpace('A');
        self::assertNull($rules->findLatestRevision($spaceId));
        $rules->commit($spaceId, [RuleCommitEntry::put('human', $this->body('One'))]);
        $latest = $rules->findLatestRevision($spaceId);
        self::assertNotNull($latest);
        self::assertSame(1, $latest->getRevision());
        try {
            $rules->findLatestRevision(999);
            self::fail('missing space must fail');
        } catch (RuleNotFoundException $exception) {
            self::assertSame('RULE_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * updateSpace и лента с ruleCount.
     *
     * @return void
     */
    public function testUpdateSpaceAndRevisionList(): void
    {
        $rules = $this->rules();
        $spaceId = $rules->addSpace('A');
        self::assertSame([], $rules->getRevisionList($spaceId));
        $rules->updateSpace($spaceId, '  B  ');
        $rules->commit($spaceId, [
            RuleCommitEntry::put('human', $this->body('One')),
            RuleCommitEntry::put('orc', $this->body('Two')),
        ]);
        $summaries = $rules->getRevisionList($spaceId);
        self::assertCount(1, $summaries);
        self::assertSame(1, $summaries[0]->getRevision());
        self::assertSame(2, $summaries[0]->getRuleCount());
        try {
            $rules->updateSpace($spaceId, ' ');
            self::fail('empty title must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Tombstone во второй ревизии.
     *
     * @return void
     */
    public function testTombstoneInSecondRevision(): void
    {
        $rules = $this->rules();
        $spaceId = $rules->addSpace('A');
        $rules->commit($spaceId, [RuleCommitEntry::put('human', $this->body('Live'))]);
        $rules->commit($spaceId, [RuleCommitEntry::put('human', $this->body('Dead'), false)]);
        self::assertTrue($rules->getRevision($spaceId, 1)->getItems()[0]->isActive());
        self::assertFalse($rules->getRevision($spaceId, 2)->getItems()[0]->isActive());
    }

    /**
     * boot и порты.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectRule(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $locator = $application->getLocator();
        $rules = $locator->get(IRuleContainer::class)->get(IRules::class);
        self::assertInstanceOf(IRules::class, $rules);
        $this->rules = $rules;
        $keywords = $locator->get(IKeywordContainer::class)->get(IKeywords::class);
        self::assertInstanceOf(IKeywords::class, $keywords);
        $this->keywords = $keywords;
        $mechanics = $locator->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $this->mechanics = $mechanics;
        $smartTableContainer = $locator->get(ISmartTableContainer::class);
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
     * Keyword, Mechanic, Rule.
     *
     * @return void
     */
    private function installSchemas(): void
    {
        $gateway = $this->smartTableGateway();
        (new KeywordSchema($gateway->open(KeywordTable::class)->schema()))->install();
        (new MechanicSchema($gateway->open(MechanicTable::class)->schema()))->install();
        (new RuleSchema(
            $gateway->open(RuleTable::class)->schema(),
            $gateway->open(RuleSpaceTable::class)->schema(),
            $gateway->open(RuleVersionTable::class)->schema(),
            $gateway->open(RuleRevisionTable::class)->schema(),
            $gateway->open(RuleRevisionItemTable::class)->schema(),
        ))->install();
    }

    /**
     * Снятие по FK.
     *
     * @return void
     */
    private function dropTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $gateway = $this->smartTableGateway;
        $this->deleteIfExists($gateway->open(RuleRevisionItemTable::class));
        $this->deleteIfExists($gateway->open(RuleRevisionTable::class));
        $this->deleteIfExists($gateway->open(RuleVersionTable::class));
        $this->deleteIfExists($gateway->open(RuleSpaceTable::class));
        $this->deleteIfExists($gateway->open(RuleTable::class));
        $this->deleteIfExists($gateway->open(MechanicTable::class));
        $this->deleteIfExists($gateway->open(KeywordTable::class));
    }

    /**
     * deleteTable если есть.
     *
     * @param IOpenedTable $openedTable Карта.
     *
     * @return void
     */
    private function deleteIfExists(IOpenedTable $openedTable): void
    {
        if ($openedTable->schema()->exists()) {
            $openedTable->schema()->deleteTable();
        }
    }

    /**
     * Тело снимка.
     *
     * @param string $name Подпись.
     * @param array<int, int> $keywordIds Признаки.
     * @param int|null $mechanicId Механика.
     * @param array<string, mixed> $mechanicPayload Payload.
     *
     * @return RuleVersionBody Снимок.
     */
    private function body(
        string $name,
        array $keywordIds = [],
        ?int $mechanicId = null,
        array $mechanicPayload = [],
    ): RuleVersionBody {
        return new RuleVersionBody(
            'ability',
            $name,
            '',
            [],
            $keywordIds,
            $mechanicId,
            $mechanicPayload,
            'needs_work',
        );
    }

    /**
     * Фасад правил.
     *
     * @return IRules Фасад.
     */
    private function rules(): IRules
    {
        self::assertInstanceOf(IRules::class, $this->rules);

        return $this->rules;
    }

    /**
     * Признаки.
     *
     * @return IKeywords Фасад.
     */
    private function keywords(): IKeywords
    {
        self::assertInstanceOf(IKeywords::class, $this->keywords);

        return $this->keywords;
    }

    /**
     * Механики.
     *
     * @return IMechanics Фасад.
     */
    private function mechanics(): IMechanics
    {
        self::assertInstanceOf(IMechanics::class, $this->mechanics);

        return $this->mechanics;
    }

    /**
     * Шлюз.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }
}
