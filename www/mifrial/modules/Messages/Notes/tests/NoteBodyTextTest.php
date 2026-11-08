<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Tests;

use Mifrial\Messages\Notes\Service\NoteBodyText;
use PHPUnit\Framework\TestCase;

final class NoteBodyTextTest extends TestCase
{
    /**
     * Теги, переносы и сущности превращаются в плоский текст.
     *
     * @return void
     */
    public function testDerivePlainText(): void
    {
        $text = NoteBodyText::derive(
            '<h2>Заголовок</h2><p>См. <span data-inline-type="character" data-inline-value="3">Гаррик</span></p><p>Вторая&nbsp;строка<br>и третья &amp; конец</p>',
        );

        self::assertSame('Заголовок См. Гаррик Вторая строка и третья & конец', $text);
    }

    /**
     * Пустой HTML даёт пустой текст.
     *
     * @return void
     */
    public function testDeriveEmpty(): void
    {
        self::assertSame('', NoteBodyText::derive(''));
        self::assertSame('', NoteBodyText::derive('<p> </p>'));
    }
}
