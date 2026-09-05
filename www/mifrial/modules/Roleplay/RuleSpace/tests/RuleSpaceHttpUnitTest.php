<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Tests;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceCommitDraftMapper;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceSlug;
use PHPUnit\Framework\TestCase;

final class RuleSpaceHttpUnitTest extends TestCase
{
    /**
     * Slug из кириллицы и явный code.
     *
     * @return void
     */
    public function testSlugResolve(): void
    {
        $slug = new RuleSpaceSlug();
        self::assertSame('mir-pravil', $slug->resolve(null, 'Мир правил'));
        self::assertSame('custom', $slug->resolve(' custom ', 'Ignored'));
        try {
            $slug->resolve(null, '!!!');
            self::fail('empty slug must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Put из JSON; removed list.
     *
     * @return void
     */
    public function testDraftMapper(): void
    {
        $mapper = new RuleSpaceCommitDraftMapper();
        $puts = $mapper->mapPuts([
            [
                'id' => 9,
                'code' => 'human',
                'type' => 'ability',
                'name' => 'Human',
                'spaceId' => 1,
            ],
        ]);
        self::assertCount(1, $puts);
        self::assertInstanceOf(RuleCommitEntry::class, $puts[0]);
        self::assertSame('human', $puts[0]->getCode());
        self::assertSame('needs_work', $puts[0]->getBody()?->getContentStatus());
        self::assertSame(['gone'], $mapper->mapRemovedCodes(['gone']));
        try {
            $mapper->mapPuts(['nope']);
            self::fail('non-object rule must fail');
        } catch (RuleSpaceInvalidException $exception) {
            self::assertSame('RULESPACE_INVALID', $exception->getErrorCode());
        }
    }
}
