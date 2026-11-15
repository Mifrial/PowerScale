<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Tests;

use Mifrial\Roleplay\Lore\Schema\LoreSchema;
use Mifrial\Roleplay\Lore\Table\LoreArticleTable;
use Mifrial\Roleplay\Lore\Table\LoreLinkTable;
use Mifrial\Roleplay\Lore\Table\LoreSectionTable;
use PHPUnit\Framework\TestCase;

/**
 * Карты модуля собираются без ошибок.
 */
final class LoreTablesTest extends TestCase
{
    /**
     * Все карты перечисляют id и уникальные ключи.
     *
     * @return void
     */
    public function testSchemasBuild(): void
    {
        foreach (LoreSchema::getTableClasses() as $tableClass) {
            $table = new $tableClass();
            self::assertArrayHasKey('id', $table->getMap());
        }

        $section = new LoreSectionTable();
        self::assertSame([['world_id', 'code']], $section->getUniqueKeys());

        $article = new LoreArticleTable();
        self::assertSame([['world_id', 'code']], $article->getUniqueKeys());
        self::assertArrayHasKey('readers', $article->getMap());
        self::assertArrayHasKey('keywords', $article->getMap());

        $link = new LoreLinkTable();
        self::assertSame([['source_article_id', 'target_type', 'target_code']], $link->getUniqueKeys());
    }
}
