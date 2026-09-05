<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Tests;

use Mifrial\Core\Cache\Interface\Container\ICacheContainer;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Core\User\Tests\UserMysqlTables;
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
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\Rule\Schema\RuleSchema;
use Mifrial\Roleplay\Rule\Table\RuleRevisionItemTable;
use Mifrial\Roleplay\Rule\Table\RuleRevisionTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use Mifrial\Roleplay\Rule\Table\RuleTable;
use Mifrial\Roleplay\Rule\Table\RuleVersionTable;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogPlacement;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogSection;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpacePatch;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceSelection;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use Mifrial\Roleplay\RuleSpace\Schema\RuleSpaceSchema;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogItemTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogSectionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceMetaTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceRevisionCatalogTable;
use PHPUnit\Framework\TestCase;

final class RuleSpaceMysqlTest extends TestCase
{
    private ?IRuleSpaces $ruleSpaces = null;

    private ?IRules $rules = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    private ?ICacheStore $cacheStore = null;

    private ?IUserAccounts $userAccounts = null;

    private int $ownerUserId = 0;

    /**
     * Подключается к MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectPorts();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for RuleSpace tests');
        }

        $this->dropTables();
        $this->flushRuleSliceCache();
        $this->installSchemas();
        $this->ownerUserId = $this->userAccounts()->addFromInput([
            'login' => 'owner',
            'name' => 'Owner',
        ]);
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
     * add без inherit, get, getByCode, title часов.
     *
     * @return void
     */
    public function testAddGetAndClockTitle(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $world = $this->addWorld('  razrabotka  ', '  Разработка  ', 'desc');
        self::assertSame('razrabotka', $world->getCode());
        self::assertSame($this->ownerUserId, $world->getOwnerId());
        self::assertSame('Разработка', $world->getName());
        self::assertSame('desc', $world->getDescription());
        self::assertTrue($world->isActive());
        self::assertSame($world->getId(), $ruleSpaces->get($world->getId())->getId());
        self::assertSame($world->getId(), $ruleSpaces->getByCode(' razrabotka ')->getId());
        $clockRow = $this->smartTableGateway()->open(RuleSpaceTable::class)->records()->getById($world->getId());
        self::assertIsArray($clockRow);
        self::assertSame('Разработка', $clockRow['title']);
    }

    /**
     * update name/description, code неизменен, пустой patch.
     *
     * @return void
     */
    public function testUpdateMetaAndEmptyPatch(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $world = $this->addWorld('world', 'World', 'old');
        $updated = $ruleSpaces->update($world->getId(), RuleSpacePatch::fromNormalized([
            'name' => '  Next  ',
            'description' => 'new',
        ]));
        self::assertSame('world', $updated->getCode());
        self::assertSame('Next', $updated->getName());
        self::assertSame('new', $updated->getDescription());
        $clockRow = $this->smartTableGateway()->open(RuleSpaceTable::class)->records()->getById($world->getId());
        self::assertIsArray($clockRow);
        self::assertSame('Next', $clockRow['title']);
        $described = $ruleSpaces->update($world->getId(), RuleSpacePatch::fromNormalized([
            'description' => '',
        ]));
        self::assertSame('Next', $described->getName());
        self::assertSame('', $described->getDescription());
        try {
            $ruleSpaces->update($world->getId(), RuleSpacePatch::fromNormalized([]));
            self::fail('empty patch must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        try {
            $ruleSpaces->update($world->getId(), RuleSpacePatch::fromNormalized(['name' => '  ']));
            self::fail('empty name must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * deactivate идемпотентен; commit после выключения.
     *
     * @return void
     */
    public function testDeactivateKeepsCommit(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $world = $this->addWorld('world', 'World');
        $ruleSpaces->deactivate($world->getId());
        $ruleSpaces->deactivate($world->getId());
        self::assertFalse($ruleSpaces->get($world->getId())->isActive());
        $ruleSpaces->commit($world->getId(), [RuleCommitEntry::put('human', $this->body('Human'))]);
        self::assertSame('human', $ruleSpaces->getRevision($world->getId(), 1)->getItems()[0]->getCode());
    }

    /**
     * Лента: пусто, DESC, tombstone в count; сирота часов.
     *
     * @return void
     */
    public function testRevisionListAndOrphanMeta(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $world = $this->addWorld('world', 'World');
        self::assertSame([], $ruleSpaces->getRevisionList($world->getId()));
        $ruleSpaces->commit($world->getId(), [
            RuleCommitEntry::put('human', $this->body('Live')),
            RuleCommitEntry::put('orc', $this->body('Orc')),
        ]);
        $ruleSpaces->commit($world->getId(), [
            RuleCommitEntry::put('human', $this->body('Dead'), false),
            RuleCommitEntry::keep($ruleSpaces->getRevision($world->getId(), 1)->getItems()[1]->getVersionId()),
        ]);
        $summaries = $ruleSpaces->getRevisionList($world->getId());
        self::assertCount(2, $summaries);
        self::assertSame(2, $summaries[0]->getRevision());
        self::assertSame(2, $summaries[0]->getRuleCount());
        self::assertSame(1, $summaries[1]->getRevision());
        self::assertSame(2, $summaries[1]->getRuleCount());

        $orphanId = $this->rules()->addSpace('Orphan');
        $emptyPatch = RuleSpacePatch::fromNormalized(['description' => 'x']);
        try {
            $ruleSpaces->update($orphanId, $emptyPatch);
            self::fail('orphan update must fail');
        } catch (RuleSpaceNotFoundException $exception) {
            self::assertSame('RULESPACE_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $ruleSpaces->deactivate($orphanId);
            self::fail('orphan deactivate must fail');
        } catch (RuleSpaceNotFoundException $exception) {
            self::assertSame('RULESPACE_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $ruleSpaces->getRevisionList($orphanId);
            self::fail('orphan list must fail');
        } catch (RuleSpaceNotFoundException $exception) {
            self::assertSame('RULESPACE_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Дубль code и пустые поля.
     *
     * @return void
     */
    public function testDuplicateAndEmptyAreInvalid(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $this->addWorld('alpha', 'Alpha');
        try {
            $this->addWorld('alpha', 'Other');
            self::fail('duplicate code must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        try {
            $this->addWorld('  ', 'Name');
            self::fail('empty code must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        try {
            $this->addWorld('beta', '  ');
            self::fail('empty name must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        try {
            $this->addWorld('gamma', 'Gamma', '', 0);
            self::fail('inherit 0 must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        try {
            $this->ruleSpaces()->add('delta', 'Delta', 0);
            self::fail('owner 0 must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        try {
            $this->ruleSpaces()->add('epsilon', 'Epsilon', 999);
            self::fail('missing owner must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Inherit keep: те же version_id, родительский put не трогает ребёнка.
     *
     * @return void
     */
    public function testInheritKeepsVersionIds(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $parent = $this->addWorld('parent', 'Parent');
        $first = $ruleSpaces->commit($parent->getId(), [
            RuleCommitEntry::put('human', $this->body('Human')),
            RuleCommitEntry::put('orc', $this->body('Orc')),
        ]);
        self::assertSame(1, $first->getRevision());
        $parentItems = $ruleSpaces->getRevision($parent->getId(), 1)->getItems();
        $child = $this->addWorld('child', 'Child', '', $parent->getId());
        $childItems = $ruleSpaces->getRevision($child->getId(), 1)->getItems();
        self::assertSame(
            array_map(static fn ($item): int => $item->getVersionId(), $parentItems),
            array_map(static fn ($item): int => $item->getVersionId(), $childItems),
        );
        self::assertSame($parentItems[0]->getEntityId(), $childItems[0]->getEntityId());
        $ruleSpaces->commit($parent->getId(), [
            RuleCommitEntry::put('human', $this->body('Human 2')),
            RuleCommitEntry::keep($parentItems[1]->getVersionId()),
        ]);
        self::assertSame('Human', $ruleSpaces->getRevision($child->getId(), 1)->getItems()[0]->getName());
        self::assertSame('Human 2', $ruleSpaces->getRevision($parent->getId(), 2)->getItems()[0]->getName());
    }

    /**
     * Каталог: шаринг, новый снимок, catalog-only, inherit copy, цикл.
     *
     * @return void
     */
    public function testCatalogShareChangeAndInherit(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $world = $this->addWorld('world', 'World');
        $tree = $this->oneSectionCatalog('combat', 'Бой', 'human');
        $first = $ruleSpaces->commit(
            $world->getId(),
            [RuleCommitEntry::put('human', $this->body('Human'))],
            $tree,
        );
        self::assertSame(1, $first->getRevision());
        self::assertSame('combat', $ruleSpaces->getCatalog($world->getId(), 1)->getSections()[0]->getCode());
        $sectionCount = $this->sectionRowCount($world->getId());
        $human = $ruleSpaces->getRevision($world->getId(), 1)->getItems()[0];
        $ruleSpaces->commit($world->getId(), [
            RuleCommitEntry::keep($human->getVersionId()),
            RuleCommitEntry::put('orc', $this->body('Orc')),
        ]);
        self::assertSame('combat', $ruleSpaces->getCatalog($world->getId(), 2)->getSections()[0]->getCode());
        self::assertSame($sectionCount, $this->sectionRowCount($world->getId()));
        $orc = $ruleSpaces->getRevision($world->getId(), 2)->getItems()[1];
        $catalogOnly = $ruleSpaces->commit(
            $world->getId(),
            [
                RuleCommitEntry::keep($human->getVersionId()),
                RuleCommitEntry::keep($orc->getVersionId()),
            ],
            $this->oneSectionCatalog('magic', 'Магия', 'human'),
        );
        self::assertSame(3, $catalogOnly->getRevision());
        self::assertSame('combat', $ruleSpaces->getCatalog($world->getId(), 1)->getSections()[0]->getCode());
        self::assertSame('magic', $ruleSpaces->getCatalog($world->getId(), 3)->getSections()[0]->getCode());
        self::assertSame(
            $human->getVersionId(),
            $ruleSpaces->getRevision($world->getId(), 3)->getItems()[0]->getVersionId(),
        );

        $parent = $this->addWorld('p2', 'P2');
        $ruleSpaces->commit(
            $parent->getId(),
            [RuleCommitEntry::put('human', $this->body('Human'))],
            $this->oneSectionCatalog('combat', 'Бой', 'human'),
        );
        $child = $this->addWorld('c2', 'C2', '', $parent->getId());
        self::assertSame('combat', $ruleSpaces->getCatalog($child->getId(), 1)->getSections()[0]->getCode());
        self::assertNotSame($this->firstSectionId($parent->getId()), $this->firstSectionId($child->getId()));
        $ruleSpaces->commit(
            $parent->getId(),
            [RuleCommitEntry::keep($ruleSpaces->getRevision($parent->getId(), 1)->getItems()[0]->getVersionId())],
            $this->oneSectionCatalog('later', 'Later', 'human'),
        );
        self::assertSame('combat', $ruleSpaces->getCatalog($child->getId(), 1)->getSections()[0]->getCode());

        try {
            $ruleSpaces->commit(
                $world->getId(),
                [RuleCommitEntry::put('elf', $this->body('Elf'))],
                RuleSpaceCatalog::fromParts([
                    new RuleSpaceCatalogSection('a', 'A', 'b', 0, null),
                    new RuleSpaceCatalogSection('b', 'B', 'a', 1, null),
                ], []),
            );
            self::fail('cycle must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Пустой снимок и no-op catalog-only.
     *
     * @return void
     */
    public function testEmptyCatalogAndNoOp(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $world = $this->addWorld('empty', 'Empty');
        $ruleSpaces->commit($world->getId(), [RuleCommitEntry::put('human', $this->body('Human'))]);
        self::assertSame([], $ruleSpaces->getCatalog($world->getId(), 1)->getSections());
        $human = $ruleSpaces->getRevision($world->getId(), 1)->getItems()[0];
        try {
            $ruleSpaces->commit(
                $world->getId(),
                [RuleCommitEntry::keep($human->getVersionId())],
                RuleSpaceCatalog::empty(),
            );
            self::fail('no-op catalog must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        $bare = $this->addWorld('bare', 'Bare');
        try {
            $ruleSpaces->commit($bare->getId(), [], $this->oneSectionCatalog('x', 'X', 'human'));
            self::fail('catalog-only without revision must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Inherit копирует tombstone тем же version_id.
     *
     * @return void
     */
    public function testInheritCopiesTombstone(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $parent = $this->addWorld('parent', 'Parent');
        $ruleSpaces->commit($parent->getId(), [
            RuleCommitEntry::put('human', $this->body('Dead'), false),
        ]);
        $parentItem = $ruleSpaces->getRevision($parent->getId(), 1)->getItems()[0];
        $child = $this->addWorld('child', 'Child', '', $parent->getId());
        $childItem = $ruleSpaces->getRevision($child->getId(), 1)->getItems()[0];
        self::assertSame($parentItem->getVersionId(), $childItem->getVersionId());
        self::assertFalse($childItem->isActive());
    }

    /**
     * Inherit от мира без ревизии.
     *
     * @return void
     */
    public function testInheritFromEmptyWorldHasNoRevision(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $parent = $this->addWorld('parent', 'Parent');
        $child = $this->addWorld('child', 'Child', '', $parent->getId());
        try {
            $ruleSpaces->getRevision($child->getId(), 1);
            self::fail('empty inherit must not create revision');
        } catch (RuleSpaceNotFoundException $exception) {
            self::assertSame('RULESPACE_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Нет sidecar у родителя.
     *
     * @return void
     */
    public function testInheritUnknownOrClockOnlyIsNotFound(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        try {
            $this->addWorld('child', 'Child', '', 999);
            self::fail('unknown parent must fail');
        } catch (RuleSpaceNotFoundException $exception) {
            self::assertSame('RULESPACE_NOT_FOUND', $exception->getErrorCode());
        }

        $clockId = $this->rules()->addSpace('Orphan');
        try {
            $this->addWorld('child', 'Child', '', $clockId);
            self::fail('clock-only parent must fail');
        } catch (RuleSpaceNotFoundException $exception) {
            self::assertSame('RULESPACE_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Откат внешней TX inherit.
     *
     * @return void
     */
    public function testInheritOuterRollback(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $parent = $this->addWorld('parent', 'Parent');
        $ruleSpaces->commit($parent->getId(), [RuleCommitEntry::put('human', $this->body('Human'))]);
        try {
            $this->smartTableGateway()->transaction(function () use ($ruleSpaces, $parent): void {
                $this->addWorld('child', 'Child', '', $parent->getId());
                throw new RuleSpaceInvalidException('forced rollback');
            });
            self::fail('outer transaction must rethrow');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        try {
            $ruleSpaces->getByCode('child');
            self::fail('rolled inherit must not leave sidecar');
        } catch (RuleSpaceNotFoundException $exception) {
            self::assertSame('RULESPACE_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * commit и findInRevision; сирота часов.
     *
     * @return void
     */
    public function testCommitAndOrphanClockSpace(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $world = $this->addWorld('world', 'World');
        $ruleSpaces->commit($world->getId(), [RuleCommitEntry::put('human', $this->body('Human'))]);
        $found = $ruleSpaces->findInRevision($world->getId(), 1, 'human');
        self::assertSame('human', $found->getCode());
        $human = $ruleSpaces->getRevision($world->getId(), 1)->getItems()[0];
        $ruleSpaces->commit($world->getId(), [
            RuleCommitEntry::keep($human->getVersionId()),
            RuleCommitEntry::put('orc', $this->body('Orc')),
        ]);
        self::assertSame('orc', $ruleSpaces->findInRevision($world->getId(), 2, 'orc')->getCode());

        $orphanId = $this->rules()->addSpace('Orphan');
        try {
            $ruleSpaces->get($orphanId);
            self::fail('orphan get must fail');
        } catch (RuleSpaceNotFoundException $exception) {
            self::assertSame('RULESPACE_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $ruleSpaces->commit($orphanId, [RuleCommitEntry::put('elf', $this->body('Elf'))]);
            self::fail('orphan commit must fail');
        } catch (RuleSpaceNotFoundException $exception) {
            self::assertSame('RULESPACE_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $ruleSpaces->commit($world->getId(), []);
            self::fail('empty commit must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * commitSelected: put одного, keep соседа, новый code.
     *
     * @return void
     */
    public function testCommitSelectedPutKeepAndNew(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $world = $this->addWorld('world', 'World');
        $ruleSpaces->commit($world->getId(), [
            RuleCommitEntry::put('human', $this->body('Human')),
            RuleCommitEntry::put('orc', $this->body('Orc')),
        ]);
        $base = $ruleSpaces->getRevision($world->getId(), 1);
        $orcVersionId = $base->getItems()[1]->getVersionId();
        $published = $ruleSpaces->commitSelected($world->getId(), RuleSpaceSelection::fromParts(1, [
            RuleCommitEntry::put('human', $this->body('Human 2')),
            RuleCommitEntry::put('elf', $this->body('Elf')),
        ]));
        self::assertSame(2, $published->getRevision());
        $slice = $ruleSpaces->getRevision($world->getId(), 2);
        self::assertSame('Human 2', $slice->getItems()[0]->getName());
        self::assertSame($orcVersionId, $slice->getItems()[1]->getVersionId());
        self::assertSame('elf', $slice->getItems()[2]->getCode());
    }

    /**
     * Tombstone, пустой выбор vs latest, мир без ревизии.
     *
     * @return void
     */
    public function testCommitSelectedTombstoneAndEmpty(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $world = $this->addWorld('world', 'World');
        try {
            $ruleSpaces->commitSelected($world->getId(), RuleSpaceSelection::fromParts(1, []));
            self::fail('select without revision must fail');
        } catch (RuleSpaceNotFoundException $exception) {
            self::assertSame('RULESPACE_NOT_FOUND', $exception->getErrorCode());
        }

        $ruleSpaces->commit($world->getId(), [
            RuleCommitEntry::put('human', $this->body('Human')),
            RuleCommitEntry::put('orc', $this->body('Orc')),
        ]);
        $orcVersionId = $ruleSpaces->getRevision($world->getId(), 1)->getItems()[1]->getVersionId();
        $ruleSpaces->commitSelected($world->getId(), RuleSpaceSelection::fromParts(1, [], ['human']));
        $slice = $ruleSpaces->getRevision($world->getId(), 2);
        self::assertFalse($slice->getItems()[0]->isActive());
        self::assertSame($orcVersionId, $slice->getItems()[1]->getVersionId());
        try {
            $ruleSpaces->commitSelected($world->getId(), RuleSpaceSelection::fromParts(2, []));
            self::fail('all keep vs latest must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }

        $ruleSpaces->commit($world->getId(), [
            RuleCommitEntry::put('human', $this->body('Human 3')),
            RuleCommitEntry::keep($orcVersionId),
        ]);
        $rolled = $ruleSpaces->commitSelected($world->getId(), RuleSpaceSelection::fromParts(1, []));
        self::assertSame(4, $rolled->getRevision());
        self::assertSame('Human', $ruleSpaces->getRevision($world->getId(), 4)->getItems()[0]->getName());
    }

    /**
     * boot и порты.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectPorts(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $locator = $application->getLocator();
        $ruleSpaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
        self::assertInstanceOf(IRuleSpaces::class, $ruleSpaces);
        $this->ruleSpaces = $ruleSpaces;
        $rules = $locator->get(IRuleContainer::class)->get(IRules::class);
        self::assertInstanceOf(IRules::class, $rules);
        $this->rules = $rules;
        $keywords = $locator->get(IKeywordContainer::class)->get(IKeywords::class);
        self::assertInstanceOf(IKeywords::class, $keywords);
        $mechanics = $locator->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $smartTableContainer = $locator->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->smartTableGateway = $smartTableGateway;
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            self::markTestSkipped('test connection is not Illuminate');
        }

        $databaseConnection->ping();
        $cacheStore = $locator->get(ICacheContainer::class)->get(ICacheStore::class);
        self::assertInstanceOf(ICacheStore::class, $cacheStore);
        $this->cacheStore = $cacheStore;
        $userAccounts = $locator->get(IUserContainer::class)->get(IUserAccounts::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        $this->userAccounts = $userAccounts;
    }

    /**
     * Keyword, Mechanic, Rule, sidecar.
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
        (new KeywordSchema($gateway->open(KeywordTable::class)->schema()))->install();
        (new MechanicSchema($gateway->open(MechanicTable::class)->schema()))->install();
        (new RuleSchema(
            $gateway->open(RuleTable::class)->schema(),
            $gateway->open(RuleSpaceTable::class)->schema(),
            $gateway->open(RuleVersionTable::class)->schema(),
            $gateway->open(RuleRevisionTable::class)->schema(),
            $gateway->open(RuleRevisionItemTable::class)->schema(),
        ))->install();
        (new RuleSpaceSchema(
            $gateway->open(RuleSpaceMetaTable::class)->schema(),
            $gateway->open(RuleSpaceCatalogSectionTable::class)->schema(),
            $gateway->open(RuleSpaceCatalogItemTable::class)->schema(),
            $gateway->open(RuleSpaceRevisionCatalogTable::class)->schema(),
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
        $this->deleteIfExists($gateway->open(RuleSpaceCatalogItemTable::class));
        $this->deleteIfExists($gateway->open(RuleSpaceRevisionCatalogTable::class));
        $this->deleteIfExists($gateway->open(RuleSpaceCatalogSectionTable::class));
        $this->deleteIfExists($gateway->open(RuleSpaceMetaTable::class));
        $this->deleteIfExists($gateway->open(RuleRevisionItemTable::class));
        $this->deleteIfExists($gateway->open(RuleRevisionTable::class));
        $this->deleteIfExists($gateway->open(RuleVersionTable::class));
        $this->deleteIfExists($gateway->open(RuleSpaceTable::class));
        $this->deleteIfExists($gateway->open(RuleTable::class));
        $this->deleteIfExists($gateway->open(MechanicTable::class));
        $this->deleteIfExists($gateway->open(KeywordTable::class));
        UserMysqlTables::drop($gateway);
    }

    /**
     * Сбрасывает кэш срезов часов (id space после drop начинаются с 1).
     *
     * @return void
     */
    private function flushRuleSliceCache(): void
    {
        if (!$this->cacheStore instanceof ICacheStore || !$this->cacheStore->isUsable()) {
            return;
        }

        $cacheKeys = [];
        for ($spaceId = 1; $spaceId <= 30; ++$spaceId) {
            for ($revision = 1; $revision <= 10; ++$revision) {
                $cacheKeys[] = 'vs:rule:' . $spaceId . ':' . $revision;
            }
        }

        $this->cacheStore->deleteKeys($cacheKeys);
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
     * Мир с владельцем теста.
     *
     * @param string $code Ключ.
     * @param string $name Подпись.
     * @param string $description Текст.
     * @param int|null $inheritFromSpaceId Родитель.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function addWorld(
        string $code,
        string $name,
        string $description = '',
        ?int $inheritFromSpaceId = null,
    ): RuleSpaceRecord {
        return $this->ruleSpaces()->add(
            $code,
            $name,
            $this->ownerUserId,
            $description,
            $inheritFromSpaceId,
        );
    }

    /**
     * Учётки.
     *
     * @return IUserAccounts Фасад.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Тело снимка.
     *
     * @param string $name Подпись.
     *
     * @return RuleVersionBody Снимок.
     */
    private function body(string $name): RuleVersionBody
    {
        return new RuleVersionBody('ability', $name, '', [], [], null, [], 'needs_work');
    }

    /**
     * Одно узловое дерево и карточка.
     *
     * @param string $sectionCode Код.
     * @param string $sectionName Подпись.
     * @param string $ruleCode Правило.
     *
     * @return RuleSpaceCatalog Снимок.
     */
    private function oneSectionCatalog(string $sectionCode, string $sectionName, string $ruleCode): RuleSpaceCatalog
    {
        return RuleSpaceCatalog::fromParts(
            [new RuleSpaceCatalogSection($sectionCode, $sectionName, null, 0, null)],
            [new RuleSpaceCatalogPlacement($ruleCode, $sectionCode, 0)],
        );
    }

    /**
     * Число узлов мира.
     *
     * @param int $spaceId Мир.
     *
     * @return int Число.
     */
    private function sectionRowCount(int $spaceId): int
    {
        return count($this->smartTableGateway()->open(RuleSpaceCatalogSectionTable::class)->records()->getList(
            ListQuery::fromOptions([
                'filter' => ['=space_id' => $spaceId],
                'limit' => ListQuery::MAX_LIMIT,
            ]),
        )->rows());
    }

    /**
     * Id первого узла мира.
     *
     * @param int $spaceId Мир.
     *
     * @return int Id.
     */
    private function firstSectionId(int $spaceId): int
    {
        $row = $this->smartTableGateway()->open(RuleSpaceCatalogSectionTable::class)->records()->getFirst(
            ListQuery::fromOptions([
                'filter' => ['=space_id' => $spaceId],
                'sort' => ['id' => 'asc'],
                'limit' => 1,
            ]),
        );
        self::assertIsArray($row);

        return (int) $row['id'];
    }

    /**
     * Фасад миров.
     *
     * @return IRuleSpaces Фасад.
     */
    private function ruleSpaces(): IRuleSpaces
    {
        self::assertInstanceOf(IRuleSpaces::class, $this->ruleSpaces);

        return $this->ruleSpaces;
    }

    /**
     * Часы правила.
     *
     * @return IRules Фасад.
     */
    private function rules(): IRules
    {
        self::assertInstanceOf(IRules::class, $this->rules);

        return $this->rules;
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
