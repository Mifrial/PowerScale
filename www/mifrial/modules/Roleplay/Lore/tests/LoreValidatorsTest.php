<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Tests;

use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;
use Mifrial\Roleplay\Lore\Service\LoreArticleValidator;
use Mifrial\Roleplay\Lore\Service\LoreTreeValidator;
use Mifrial\Roleplay\Lore\Service\LoreWorldValidator;
use PHPUnit\Framework\TestCase;

/**
 * Проверки снимков мира, дерева и статьи.
 */
final class LoreValidatorsTest extends TestCase
{
    /**
     * Мир: умолчания и запрет лишнего ключа.
     *
     * @return void
     */
    public function testWorldValidator(): void
    {
        $validator = new LoreWorldValidator();
        $values = $validator->validate(['code' => 'austerland', 'name' => 'Аустерленд']);

        self::assertSame('austerland', $values->getCode());
        self::assertSame('private', $values->getVisibility());
        self::assertSame('', $values->getSpaceCode());

        $this->expectException(LoreInvalidException::class);
        $validator->validate(['code' => 'Bad Code', 'name' => 'x']);
    }

    /**
     * Мир: лишний ключ отвергается.
     *
     * @return void
     */
    public function testWorldValidatorRejectsUnknownKey(): void
    {
        $this->expectException(LoreInvalidException::class);

        (new LoreWorldValidator())->validate(['code' => 'a', 'name' => 'A', 'ownerId' => 1]);
    }

    /**
     * Дерево: порядок, родители и значения.
     *
     * @return void
     */
    public function testTreeValidatorBuildsNodes(): void
    {
        $values = (new LoreTreeValidator())->validate([
            ['code' => 'history', 'name' => 'История', 'sortOrder' => 1],
            ['code' => 'wars', 'name' => 'Войны', 'parentCode' => 'history'],
        ]);

        self::assertCount(2, $values);
        self::assertSame('history', $values[1]->getParentCode());
        self::assertSame(0, $values[1]->getSortOrder());
    }

    /**
     * Дерево: дубль кода.
     *
     * @return void
     */
    public function testTreeValidatorRejectsDuplicateCode(): void
    {
        $this->expectException(LoreInvalidException::class);

        (new LoreTreeValidator())->validate([
            ['code' => 'history', 'name' => 'История'],
            ['code' => 'history', 'name' => 'Ещё история'],
        ]);
    }

    /**
     * Дерево: неизвестный родитель.
     *
     * @return void
     */
    public function testTreeValidatorRejectsUnknownParent(): void
    {
        $this->expectException(LoreInvalidException::class);

        (new LoreTreeValidator())->validate([
            ['code' => 'wars', 'name' => 'Войны', 'parentCode' => 'missing'],
        ]);
    }

    /**
     * Дерево: цикл.
     *
     * @return void
     */
    public function testTreeValidatorRejectsCycle(): void
    {
        $this->expectException(LoreInvalidException::class);

        (new LoreTreeValidator())->validate([
            ['code' => 'a', 'name' => 'A', 'parentCode' => 'b'],
            ['code' => 'b', 'name' => 'B', 'parentCode' => 'a'],
        ]);
    }

    /**
     * Дерево: глубина.
     *
     * @return void
     */
    public function testTreeValidatorRejectsDeepTree(): void
    {
        $this->expectException(LoreInvalidException::class);

        (new LoreTreeValidator())->validate([
            ['code' => 'a', 'name' => 'A'],
            ['code' => 'b', 'name' => 'B', 'parentCode' => 'a'],
            ['code' => 'c', 'name' => 'C', 'parentCode' => 'b'],
            ['code' => 'd', 'name' => 'D', 'parentCode' => 'c'],
            ['code' => 'e', 'name' => 'E', 'parentCode' => 'd'],
            ['code' => 'f', 'name' => 'F', 'parentCode' => 'e'],
        ]);
    }

    /**
     * Статья: тело, статус, читатели.
     *
     * @return void
     */
    public function testArticleValidator(): void
    {
        $values = (new LoreArticleValidator())->validate([
            'code' => 'fall',
            'title' => 'Падение',
            'bodyHtml' => '<p>См. <a data-lore-code="old-capital">столицу</a>.</p>',
            'sectionCode' => 'history',
            'keywordIds' => [3, 3, 5],
            'status' => 'published',
            'visibility' => 'secret',
            'readers' => [7],
        ]);

        self::assertSame('fall', $values->getCode());
        self::assertSame([3, 5], $values->getKeywordIds());
        self::assertSame([7], $values->getReaderIds());
        self::assertSame('secret', $values->getVisibility());
        self::assertSame('См. столицу.', $values->getBodyText());
    }

    /**
     * Статья: слишком большое тело.
     *
     * @return void
     */
    public function testArticleValidatorRejectsBigBody(): void
    {
        $this->expectException(LoreInvalidException::class);

        (new LoreArticleValidator())->validate([
            'code' => 'big',
            'title' => 'Big',
            'bodyHtml' => str_repeat('x', 60001),
        ]);
    }

    /**
     * Статья: неверная видимость.
     *
     * @return void
     */
    public function testArticleValidatorRejectsVisibility(): void
    {
        $this->expectException(LoreInvalidException::class);

        (new LoreArticleValidator())->validate([
            'code' => 'x',
            'title' => 'X',
            'visibility' => 'friends',
        ]);
    }
}
