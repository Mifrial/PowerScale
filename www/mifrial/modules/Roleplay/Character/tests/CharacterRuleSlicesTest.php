<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Service\CharacterRuleSlices;
use Mifrial\Roleplay\Keyword\Dto\KeywordRecord;
use Mifrial\Roleplay\Keyword\Exception\KeywordInvalidException;
use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionRecord;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use PHPUnit\Framework\TestCase;

final class CharacterRuleSlicesTest extends TestCase
{
    /**
     * Live по code, порядок состава, keyword ids → codes, tombstone.
     *
     * @return void
     */
    public function testLiveOrderKeywordCodesAndTombstone(): void
    {
        $ruleSpaces = $this->createMock(IRuleSpaces::class);
        $ruleSpaces->expects(self::once())->method('get')->with(5)->willReturn($this->world(true));
        $ruleSpaces->expects(self::once())->method('getRevision')->with(5, 2)->willReturn(
            $this->revisionSlice([
                $this->rule('beta', true, [3, 3, 8]),
                $this->rule('gone', false, [3]),
                $this->rule('alpha', true, [8]),
            ]),
        );
        $keywords = $this->createMock(IKeywords::class);
        $keywords->expects(self::exactly(2))->method('get')->willReturnCallback(
            function (int $keywordId): KeywordRecord {
                if ($keywordId === 3) {
                    return $this->keyword(3, 'fire', false);
                }

                if ($keywordId === 8) {
                    return $this->keyword(8, 'ice', true);
                }

                throw new KeywordNotFoundException();
            },
        );
        $slice = (new CharacterRuleSlices($ruleSpaces, $keywords))->get(5, 2);
        self::assertSame(5, $slice->getSpaceId());
        self::assertSame(2, $slice->getRevision());
        self::assertSame('world', $slice->getSpaceCode());
        self::assertSame(['beta', 'alpha'], array_map(
            static fn ($rule): string => $rule->getCode(),
            $slice->getLiveRules(),
        ));
        self::assertNotNull($slice->findLive('beta'));
        self::assertSame(['fire', 'ice'], $slice->findLive('beta')->getKeywordCodes());
        self::assertSame(['ice'], $slice->findLive('alpha')->getKeywordCodes());
        self::assertNull($slice->findLive('gone'));
        self::assertTrue($slice->hasTombstone('gone'));
        self::assertFalse($slice->hasTombstone('beta'));
        self::assertSame('ability', $slice->findLive('beta')->getType());
        self::assertSame(['k' => 1], $slice->findLive('beta')->getSpec());
        self::assertSame('needs_work', $slice->findLive('beta')->getContentStatus());
        self::assertNull($slice->findLive('beta')->getMechanicId());
    }

    /**
     * Выключенный мир: INVALID, getRevision не звать.
     *
     * @return void
     */
    public function testInactiveWorldIsInvalidWithoutRevision(): void
    {
        $ruleSpaces = $this->createMock(IRuleSpaces::class);
        $ruleSpaces->expects(self::once())->method('get')->with(5)->willReturn($this->world(false));
        $ruleSpaces->expects(self::never())->method('getRevision');
        $keywords = $this->createMock(IKeywords::class);
        $keywords->expects(self::never())->method('get');
        try {
            (new CharacterRuleSlices($ruleSpaces, $keywords))->get(5, 1);
            self::fail('inactive world must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет мира.
     *
     * @return void
     */
    public function testMissingWorldIsNotFound(): void
    {
        $ruleSpaces = $this->createMock(IRuleSpaces::class);
        $ruleSpaces->method('get')->willThrowException(new RuleSpaceNotFoundException());
        try {
            (new CharacterRuleSlices($ruleSpaces, $this->createStub(IKeywords::class)))->get(9, 1);
            self::fail('missing world must fail');
        } catch (CharacterNotFoundException $exception) {
            self::assertSame('CHARACTER_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Нет ревизии.
     *
     * @return void
     */
    public function testMissingRevisionIsNotFound(): void
    {
        $ruleSpaces = $this->createMock(IRuleSpaces::class);
        $ruleSpaces->method('get')->willReturn($this->world(true));
        $ruleSpaces->method('getRevision')->willThrowException(new RuleSpaceNotFoundException());
        try {
            (new CharacterRuleSlices($ruleSpaces, $this->createStub(IKeywords::class)))->get(5, 99);
            self::fail('missing revision must fail');
        } catch (CharacterNotFoundException $exception) {
            self::assertSame('CHARACTER_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Битый номер ревизии.
     *
     * @return void
     */
    public function testInvalidRevisionNumberIsInvalid(): void
    {
        $ruleSpaces = $this->createMock(IRuleSpaces::class);
        $ruleSpaces->method('get')->willReturn($this->world(true));
        $ruleSpaces->method('getRevision')->willThrowException(new RuleSpaceInvalidException());
        try {
            (new CharacterRuleSlices($ruleSpaces, $this->createStub(IKeywords::class)))->get(5, 0);
            self::fail('invalid revision must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет признака.
     *
     * @return void
     */
    public function testMissingKeywordIsInvalid(): void
    {
        $ruleSpaces = $this->createMock(IRuleSpaces::class);
        $ruleSpaces->method('get')->willReturn($this->world(true));
        $ruleSpaces->method('getRevision')->willReturn(
            $this->revisionSlice([$this->rule('bolt', true, [4])]),
        );
        $keywords = $this->createMock(IKeywords::class);
        $keywords->method('get')->willThrowException(new KeywordNotFoundException());
        try {
            (new CharacterRuleSlices($ruleSpaces, $keywords))->get(5, 2);
            self::fail('missing keyword must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Битая строка признака.
     *
     * @return void
     */
    public function testKeywordInvalidIsInvalid(): void
    {
        $ruleSpaces = $this->createMock(IRuleSpaces::class);
        $ruleSpaces->method('get')->willReturn($this->world(true));
        $ruleSpaces->method('getRevision')->willReturn(
            $this->revisionSlice([$this->rule('bolt', true, [4])]),
        );
        $keywords = $this->createMock(IKeywords::class);
        $keywords->method('get')->willThrowException(new KeywordInvalidException());
        try {
            (new CharacterRuleSlices($ruleSpaces, $keywords))->get(5, 2);
            self::fail('invalid keyword must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Дубль live в DTO, без сервиса.
     *
     * @return void
     */
    public function testSliceDtoRejectsDuplicateLiveCode(): void
    {
        $liveRule = new CharacterResolvedRule($this->rule('bolt', true, []), []);
        try {
            new CharacterRuleSlice(5, 1, 'world', [$liveRule, $liveRule], []);
            self::fail('duplicate live dto must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Live code в tombstone DTO.
     *
     * @return void
     */
    public function testSliceDtoRejectsLiveAndTombstoneCode(): void
    {
        $liveRule = new CharacterResolvedRule($this->rule('bolt', true, []), []);
        try {
            new CharacterRuleSlice(5, 1, 'world', [$liveRule], ['bolt' => true]);
            self::fail('live+tombstone dto must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Дубль live code.
     *
     * @return void
     */
    public function testDuplicateLiveCodeIsInvalid(): void
    {
        $ruleSpaces = $this->createMock(IRuleSpaces::class);
        $ruleSpaces->method('get')->willReturn($this->world(true));
        $ruleSpaces->method('getRevision')->willReturn(
            $this->revisionSlice([
                $this->rule('bolt', true, []),
                $this->rule('bolt', true, []),
            ]),
        );
        try {
            (new CharacterRuleSlices($ruleSpaces, $this->createStub(IKeywords::class)))->get(5, 2);
            self::fail('duplicate live must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Live и tombstone с одним code.
     *
     * @return void
     */
    public function testLiveAndTombstoneSameCodeIsInvalid(): void
    {
        $ruleSpaces = $this->createMock(IRuleSpaces::class);
        $ruleSpaces->method('get')->willReturn($this->world(true));
        $ruleSpaces->method('getRevision')->willReturn(
            $this->revisionSlice([
                $this->rule('bolt', true, []),
                $this->rule('bolt', false, []),
            ]),
        );
        try {
            (new CharacterRuleSlices($ruleSpaces, $this->createStub(IKeywords::class)))->get(5, 2);
            self::fail('live+tombstone must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Мир sidecar.
     *
     * @param bool $active Включён.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function world(bool $active): RuleSpaceRecord
    {
        return RuleSpaceRecord::fromNormalized([
            'space_id' => 5,
            'code' => 'world',
            'name' => 'World',
            'owner_id' => 1,
            'description' => '',
            'active' => $active,
            'created_at' => DateTime::now(),
        ]);
    }

    /**
     * Срез ревизии 2.
     *
     * @param array<int, RuleVersionRecord> $items Пункты.
     *
     * @return RuleRevisionSlice Срез.
     */
    private function revisionSlice(array $items): RuleRevisionSlice
    {
        return new RuleRevisionSlice(
            new RuleRevisionRecord(1, 5, 2, DateTime::now()),
            $items,
        );
    }

    /**
     * Пункт среза.
     *
     * @param string $code Ключ.
     * @param bool $active Live.
     * @param array<int, int> $keywordIds Признаки.
     *
     * @return RuleVersionRecord Пункт.
     */
    private function rule(string $code, bool $active, array $keywordIds): RuleVersionRecord
    {
        return new RuleVersionRecord(
            10,
            1,
            $code,
            $active,
            'ability',
            'Name',
            '',
            ['k' => 1],
            $keywordIds,
            null,
            [],
            'needs_work',
            DateTime::now(),
        );
    }

    /**
     * Признак.
     *
     * @param int $id Id.
     * @param string $code Ключ.
     * @param bool $active Справочник.
     *
     * @return KeywordRecord Признак.
     */
    private function keyword(int $id, string $code, bool $active): KeywordRecord
    {
        return KeywordRecord::fromNormalized([
            'id' => $id,
            'code' => $code,
            'name' => $code,
            'description' => '',
            'active' => $active,
        ]);
    }
}
