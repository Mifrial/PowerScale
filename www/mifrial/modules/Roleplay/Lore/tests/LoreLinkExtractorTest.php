<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Tests;

use Mifrial\Roleplay\Lore\Service\LoreLinkExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Извлечение ссылок из HTML-тела.
 */
final class LoreLinkExtractorTest extends TestCase
{
    /**
     * lore, rule и inline-сущности.
     *
     * @return void
     */
    public function testExtractFindsAllLinkKinds(): void
    {
        $html = '<p><a data-lore-code="old-capital">Столица</a>'
            . '<a data-rule-code="magic-resistance">Сопротивление</a>'
            . '<span data-inline-type="character" data-inline-value="7">Гаррик</span></p>';

        $links = (new LoreLinkExtractor())->extract($html);

        self::assertCount(3, $links);
        self::assertSame('lore', $links[0]->getType());
        self::assertSame('old-capital', $links[0]->getCode());
        self::assertSame('rule', $links[1]->getType());
        self::assertSame('magic-resistance', $links[1]->getCode());
        self::assertSame('character', $links[2]->getType());
        self::assertSame('7', $links[2]->getCode());
    }

    /**
     * Дубли одного типа и кода схлопываются.
     *
     * @return void
     */
    public function testExtractDeduplicates(): void
    {
        $html = '<a data-lore-code="a">A</a><a data-lore-code="a">A2</a>';

        $links = (new LoreLinkExtractor())->extract($html);

        self::assertCount(1, $links);
        self::assertSame('a', $links[0]->getCode());
    }

    /**
     * Битый HTML не роняет разбор.
     *
     * @return void
     */
    public function testExtractToleratesBrokenHtml(): void
    {
        $html = '<p><a data-lore-code="broken">без закрытия';

        $links = (new LoreLinkExtractor())->extract($html);

        self::assertCount(1, $links);
        self::assertSame('broken', $links[0]->getCode());
    }

    /**
     * Ссылки без кода и с недопустимым типом пропускаются.
     *
     * @return void
     */
    public function testExtractSkipsEmptyAndInvalid(): void
    {
        $html = '<a data-lore-code=" ">x</a>'
            . '<span data-inline-type="Bad Type" data-inline-value="1">x</span>';

        self::assertSame([], (new LoreLinkExtractor())->extract($html));
    }

    /**
     * Пустое тело.
     *
     * @return void
     */
    public function testExtractEmptyBody(): void
    {
        self::assertSame([], (new LoreLinkExtractor())->extract(''));
    }
}
