<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Tests;

use Mifrial\Roleplay\Lore\Service\LoreBodyText;
use PHPUnit\Framework\TestCase;

/**
 * Производный текст статьи.
 */
final class LoreBodyTextTest extends TestCase
{
    /**
     * Теги, сущности и пробелы.
     *
     * @return void
     */
    public function testDeriveFlattensHtml(): void
    {
        $html = '<h2>Трон</h2><p>Северный&nbsp;край<br>и <strong>юг</strong>.</p>';

        self::assertSame('Трон Северный край и юг.', LoreBodyText::derive($html));
    }

    /**
     * script и style не попадают в текст.
     *
     * @return void
     */
    public function testDeriveDropsScriptsAndStyles(): void
    {
        $html = '<style>.a{color:red}</style><p>Текст</p><script>alert(1)</script>';

        self::assertSame('Текст', LoreBodyText::derive($html));
    }

    /**
     * Пустой HTML.
     *
     * @return void
     */
    public function testDeriveEmptyHtml(): void
    {
        self::assertSame('', LoreBodyText::derive(''));
        self::assertSame('', LoreBodyText::derive('   '));
    }
}
